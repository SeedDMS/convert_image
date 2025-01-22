<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2024-2025 Uwe Steinmann <uwe@steinmann.cx>
*  All rights reserved
*
*  This script is part of the SeedDMS project. The SeedDMS project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once("inc/inc.ClassConversionServiceBase.php");
require_once(__DIR__."/vendor/autoload.php");

/**
 * Convert Image extension
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  convert_image
 */
class SeedDMS_ExtConvertImage extends SeedDMS_ExtBase {

	/**
	 * Initialization
	 *
	 * Use this method to do some initialization like setting up the hooks
	 * You have access to the following global variables:
	 * $GLOBALS['settings'] : current global configuration
	 * $GLOBALS['settings']->_extensions['index_info'] : configuration of this extension
	 * $GLOBALS['LANG'] : the language array with translations for all languages
	 * $GLOBALS['SEEDDMS_HOOKS'] : all hooks added so far
	 */
	function init() { /* {{{ */
		$GLOBALS['SEEDDMS_HOOKS']['initConversion'][] = new SeedDMS_ExtConvertImage_InitConversion;
	} /* }}} */

	function main() { /* {{{ */
	} /* }}} */
}

/**
 * Class based on Fpdf with support for parsing webp and avif files
 *
 * This class adds _parsewebp() and _parseavif() which handle webp and
 * avif image files. The approach is identical to how Fpdf handles gif
 * files. Before embedding them they are turned into png and afterwards
 * _parsepngstream() embedds them into pdf.
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  convert_image
 */
class MyFpdf extends Fpdf\Fpdf { /* {{{ */

	protected function _parsewebp($file) { /* {{{ */
		// Extract info from a WEBP file (via PNG conversion)
		if(!function_exists('imagepng'))
			$this->Error('GD extension is required for GIF support');
		if(!function_exists('imagecreatefromjpeg'))
			$this->Error('GD has no WEBP read support');
		$im = imagecreatefromwebp($file);
		if(!$im)
			$this->Error('Missing or incorrect image file: '.$file);
		imageinterlace($im,0);
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		imagedestroy($im);
		$f = fopen('php://temp','rb+');
		if(!$f)
			$this->Error('Unable to create memory stream');
		fwrite($f,$data);
		rewind($f);
		$info = $this->_parsepngstream($f,$file);
		fclose($f);
		return $info;
	} /* }}} */

	protected function _parseavif($file) { /* {{{ */
		// Extract info from a AVIF file (via PNG conversion)
		if(!function_exists('imagepng'))
			$this->Error('GD extension is required for GIF support');
		if(!function_exists('imagecreatefromavif'))
			$this->Error('GD has no AVIF read support');
		$im = imagecreatefromavif($file);
		if(!$im)
			$this->Error('Missing or incorrect image file: '.$file);
		imageinterlace($im,0);
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		imagedestroy($im);
		$f = fopen('php://temp','rb+');
		if(!$f)
			$this->Error('Unable to create memory stream');
		fwrite($f,$data);
		rewind($f);
		$info = $this->_parsepngstream($f,$file);
		fclose($f);
		return $info;
	} /* }}} */

} /* }}} */

/**
 * Class implementing a conversion service from html or markdown formats to pdf
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage convert_image
 */
class SeedDMS_ExtConvertImage_ConversionServiceToPdf extends SeedDMS_ConversionServiceBase { /* {{{ */
	/**
	 * configuration
	 */
	protected $conf;

	/**
	 * DMS
	 */
	protected $dms;

	public function __construct($dms, $conf) { /* {{{ */
		parent::__construct();
		$this->dms = $dms;
		$this->conf = $conf;
	} /* }}} */

	public function getInfo() { /* {{{ */
		return "Convert with service provided by extension convert_image based on fpdf";
	} /* }}} */

	public function getAdditionalParams() { /* {{{ */
		return [
			['name'=>'margin', 'type'=>'number', 'description'=>'Margin around image'],
		];
	} /* }}} */

	public function convert($infile, $target = null, $params = array()) { /* {{{ */
		$debug = false;
		$margin = 0;

		if(!empty($params['margin']) && intval($params['margin']) > 0)
			$margin = intval($params['margin']);

		$start = microtime(true);

		$size = getimagesize($infile);
		switch($size['mime']) {
		case 'image/gif':
			$img = imagecreatefromgif($infile);
			break;
		case 'image/png':
			$img = imagecreatefrompng($infile);
			break;
		case 'image/jpeg':
		case 'image/jpg':
			$img = imagecreatefromjpeg($infile);
			break;
		case 'image/webp': // Currently not supported by Fpdf
			$img = imagecreatefromwebp($infile);
			break;
		case 'image/avif': // Currently not supported by Fpdf
			$img = imagecreatefromavif($infile);
			break;
		}
		$dpi = imageresolution($img);
		imagedestroy($img);

		$pdf = new MyFpdf($size[0] > $size[1] ? 'l' : 'p', 'pt', [$size[0]*72/$dpi[0]+2*$margin, $size[1]*72/$dpi[1]+2*$margin]);
		$pdf->setMargins($margin, $margin);
		$txt = $dpi[0].'x'.$dpi[1].'dpi '.$size[0].'x'.$size[1].'px';
		$pdf->setTitle('Converted by SeedDMS conversion service convert_image', true);
		$pdf->setCreator('SeedDMS', true);
		$pdf->setSubject($txt);
		$pdf->AddPage();
		$pdf->Image($infile, $margin, $margin, $pdf->getPageWidth()-2*$margin, $pdf->getPageHeight()-2*$margin); //-$dpi[0], -$dpi[1]);//$size[0], $size[1]);
		if($debug) {
			$pdf->SetFont('Arial', '', 12);
			$pdf->Text(10, 20, $txt);
		}

		$end = microtime(true);

		if($this->logger) {
			$this->logger->log('Conversion from '.$this->from.' to '.$this->to.' with convert_image took '.($end-$start).' sec.', PEAR_LOG_DEBUG);
		}

		if($target) {
			return $pdf->Output($target, 'F');
		} else {
			return $pdf->Output('', 'S');
		}
		return false;
	} /* }}} */
} /* }}} */

/**
 * Class containing methods for hooks when the conversion service is initialized
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  convert_image
 */
class SeedDMS_ExtConvertImage_InitConversion { /* {{{ */

	/**
	 * Hook returning further conversion services
	 */
	public function getConversionServices($params) { /* {{{ */
		$dms = $params['dms'];
		$conf = !empty($params['settings']->_extensions['convert_image']) ? $params['settings']->_extensions['convert_image'] : [];
		$services = [];
		foreach(['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif'] as $mfrom) {
			foreach(['application/pdf'] as $mto) {
				$service = new SeedDMS_ExtConvertImage_ConversionServiceToPdf($dms, $conf);
				$service->from = $mfrom;
				$service->to = $mto;
				$services[] = $service;
			}
		}
		return $services;
	} /* }}} */

} /* }}} */
