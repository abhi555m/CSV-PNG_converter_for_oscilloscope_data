<?php

$csvuploaderror; $pngoutput;

/*
------------ FUNCTIONS ------------
*/
	
function createfromcsv($filepath, $input_height, $input_width, $overlay) {
	global $csvuploaderror;
	$csvcontent = file($filepath,FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
	$errors = true;
	$num_rows = count($csvcontent) - 2;
	$padding_top = 30; //padding in pixels on top of png
	$padding_bot = 50; //padding in pixels on bottom of png
	
	if ($input_height == 0) {
		$height = round(sqrt($num_rows)*10); //This calculation gives good results
		if ($height < 500) {
			$height = 500;
		}
	}
	else {
		$height = round($input_height);
	}
	if ($input_width == 0) {
		$width = $num_rows;
		$x_div = 1; //Pixelcolumns per datarow
	}
	else {
		$width = $input_width;
		$x_div = $width / $num_rows;
	}
	
	$max_nr_pixels = 12500000; //obtained through testing until memory error occured
	if ($height*$width > $max_nr_pixels) {
		$height = round($max_nr_pixels / $width); //maintaining width is more important than the height
	}
	
	
	$identifiers = array();
	$rowvalues = explode(",",$csvcontent[0]);
	$i = 0;
	while ($rowvalues[$i] != "") {
		$identifiers[] = $rowvalues[$i];
		$i++;
	}
	$num_columns = $i;
	
	$units = array();
	$rowvalues = explode(",",$csvcontent[1]);
	for ($i = 0; $i <= $num_columns; $i++) {
		$units[] = $rowvalues[$i];
	}
	
	$all_values = array();
	for ($i = 0; $i <= $num_columns; $i++) {
		$all_values[] = array();
	}
	
	$rawvalues = array();
	for ($row = 0; $row < $num_rows; $row++) {
		$rawvalues = explode(",",$csvcontent[$row+2],$num_columns+1);
		for ($i = 0; $i < $num_columns; $i++) {
			$tocompute = explode("e",$rawvalues[$i],3);
			$all_values[$i][] = floatval($tocompute[0]) * pow(10,intval($tocompute[1]));
		}
	}
	
	//Memory management   Very important: Free memory, so larger images can be processed
	$values = array();
	if ($x_div<1) { //compress values if wished
		for ($col = 0; $col < $num_columns; $col++) {
			for ($row = 0; $row <= $width; $row++) {
				$values[$col][$row] = $all_values[$col][(int)($row/$x_div)];
			}
		}
		$x_div = 1;
	}
	else {
		$values = $all_values;
	}
	unset($all_values);
	unset($csvcontent); 

	$img = imagecreatetruecolor($width,$height);	
	$color_white = imagecolorallocate($img,255,255,255);
	$color_black = imagecolorallocate($img,0,0,0);
	$color_red = imagecolorallocate($img,255,0,0);
	$color_blue = imagecolorallocate($img,0,0,255);
	$color_green = imagecolorallocate($img,0,255,0);
	$color_gray = imagecolorallocate($img,128,128,128);
	$color_darkyellow = imagecolorallocate($img,153,153,0);
	$color_darkgreen = imagecolorallocate($img,0,102,0);
	$color_purple = imagecolorallocate($img,102,0,102);
	$color_turkise = imagecolorallocate($img,0,102,102);
	$colors = array($color_black,$color_red,$color_blue,$color_green,$color_darkyellow,$color_purple,$color_darkgreen,$color_turkise);
	$num_colors = count($colors);
	if ($num_columns > $num_colors) {
		//To prevent index error when accessing colors
		$colors = array_fill($num_colors-1,$num_columns-$num_colors, $color_black);
	}
	$errors = imagefill($img,0,0,$color_white);
	
	
	$maxes = array();
	for ($col = 1; $col < $num_columns; $col++) {
		$maxes[] = max($values[$col]);
	}
	$max_y_value = max($maxes);
	
	$mins = array();
	for ($col = 1; $col < $num_columns; $col++) {
		$mins[] = min($values[$col]);
	}
	$min_y_value = min($mins);
	
	$y_diff = $max_y_value - $min_y_value; 
	
	$y_line_div = $y_diff/4;
	$possible = array(0.001,0.005,0.01,0.05,0.1,0.5,1,5,10,50,100,500,1000);
	for ($i = 0; $i < count($possible); $i++) {
		if ($y_line_div <= $possible[$i]) {
			$y_line_div = $possible[$i];
			break;
		}
	}
	
	if ($overlay == FALSE) { 
		//user wants channels seperated
		$y_offset = array();
		$y_offset[0] = 0; //offset of first y-axis data column, NOT in pixels
		for ($col = 2; $col < $num_columns; $col++) {
			$y_offset[$col-1] = $y_line_div * ( (int)(($y_offset[$col-2]+$maxes[$col-1]) / $y_line_div) +1); 
			//"The offset of the y-axis data column = the offset of the previous column + the max value of the previous column"
			//Then set to a horizontal division line to look nice
		}
	}
	else {
		$y_offset = array_fill(0, $num_columns-1, 0);
	}
	
	$y_div = round( ($height-$padding_top-$padding_bot)/($y_diff+$y_offset[$num_columns-2]) );
	$y_of_0V = $height-$padding_bot-(abs($min_y_value)*$y_div);
	
	
	$nr_lines_below_0V = (int) ( (($height-$padding_bot)-$y_of_0V) / ($y_line_div*$y_div) );
	$nr_lines_above_0V = (int) ( ($y_of_0V-$padding_top) / ($y_line_div*$y_div) );
	$style = array_merge(array_fill(0, 8, IMG_COLOR_TRANSPARENT), array_fill(0, 2, $color_gray));
	imagesetstyle($img, $style);
	for ($mult = -$nr_lines_above_0V; $mult <= $nr_lines_below_0V; $mult++) {
		$line_y_val = round( $y_of_0V+($mult*$y_div*$y_line_div) );
		$errors &= imageline($img,0,$line_y_val,$width,$line_y_val,IMG_COLOR_STYLED);
	}
	//Creating the horizontal 0V line
	if ($overlay == TRUE) {
		//only if all channels share one 0V line
		$errors &= imageline($img,0,$y_of_0V,$width,$y_of_0V,$color_black);
	}

	
	//Creating vertical X-axis lines
	$max_x_value = max($values[0]);
	$min_x_value = min($values[0]);
	$x_diff = $max_x_value - $min_x_value;
	$delta_x = $x_diff / $width; //change of x from one pixelcolumn to the next
	$possible = array(0.000000001,0.00000001,0.0000001,0.000001,0.00001,0.0001,0.001,0.01,0.1,1,10,100,1000);
	$x_line_repeat = 100; //We want a vertical line every ~100px
	$x_line_div = $delta_x * $x_line_repeat;
	for ($i = 0; $i < count($possible); $i++) {
		if ($x_line_div <= $possible[$i]) {
			$x_line_div = $possible[$i];
			break;
		}
	}
	$x_line_repeat = round($x_line_div / $delta_x); //We need to correct the value
	$nr_vert_lines = (int) ($width / $x_line_repeat);
	for ($mult = 1; $mult <= $nr_vert_lines; $mult++) {
		$line_x_val = $mult * $x_line_repeat;
		$errors &= imageline($img,$line_x_val,0,$line_x_val,$height,IMG_COLOR_STYLED);
	}
	
	
	//Plotting the actual data
	for ($col = 1; $col < $num_columns; $col++) {
		for ($x_val = 1; $x_val <= $width; $x_val++) {
			$errors &= imageline($img,
										$x_val-1,
										$y_of_0V-($y_div* ($values[$col][(int)(($x_val-1)/$x_div)] + $y_offset[$col]) ),
										$x_val,
										$y_of_0V-($y_div* ($values[$col][(int) ($x_val/$x_div)] + $y_offset[$col]) ),
										$colors[$col]);
		}
	}

	
	//Adding text
	$coords = array();
	$space = 20; //space in pixels between imagettftext()
	for ($col = 0; $col < $num_columns; $col++) {
		//imagettftext($image,$size,$angle,$x,$y,$color,$fontfile,$text
		if (empty($coords)) { // this is only the case for the X-axis data == $color_black
			$coords = imagettftext($img,12,0,$space,$height-20,$colors[$col],realpath($_SERVER["DOCUMENT_ROOT"])."/oxygenmono.ttf",$identifiers[$col].'('.$x_line_div.' '.$units[$col].'/div)');
		}
		else {
			$coords = imagettftext($img,12,0,$coords[2]+$space,$height-20,$colors[$col],
									realpath($_SERVER["DOCUMENT_ROOT"])."/oxygenmono.ttf",$identifiers[$col].'('.$y_line_div.' '.$units[$col].'/div)');
		}
	}
	
	
	
	$pngname = 'monoclecat-de_'.date(MdY).'_'.rand(100000000,999999999).'.png';
	$errors &= imagepng($img,realpath($_SERVER["DOCUMENT_ROOT"]).$pngname,9);
	imagedestroy($img);
	
	if ($errors != false) {
		return $pngname;
	}
	else {
		return FALSE;
	}
}


/*
------------ REQUESTHANDLER ------------
*/
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ($_POST["csvfileupload"]) {
			try {
			    // Undefined | Multiple Files | $_FILES Corruption Attack
			    // If this request falls under any of them, treat it invalid.
			    if (
			        !isset($_FILES['filename']['error']) ||
			        is_array($_FILES['filename']['error'])
			    ) {
			        throw new RuntimeException('Invalid parameters.');
			    }
				
			    // Check $_FILES['filename']['error'] value.
			    switch ($_FILES['filename']['error']) {
			        case UPLOAD_ERR_OK:
			            break;
			        case UPLOAD_ERR_NO_FILE:
			            throw new RuntimeException('No file selected.');
			        case UPLOAD_ERR_INI_SIZE:
			        case UPLOAD_ERR_FORM_SIZE:
			            throw new RuntimeException('Exceeded filesize limit.');
			        default:
			            throw new RuntimeException('Unknown errors.');
			    }

			    // You should also check filesize here.
			    if ($_FILES['filename']['size'] > 1000000) {//==1MB 
			        throw new RuntimeException('Exceeded filesize limit.');
			    }

				// DO NOT TRUST $_FILES['multfilename']['mime'] VALUE !!
				// Check MIME Type by yourself.
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				if (false === $ext = array_search(
				$finfo->file($_FILES['filename']['tmp_name']),
				array(
					'txt' => 'text/plain', 
					'csv' => 'text/csv'
				),true
				)) {
					throw new RuntimeException('Invalid file format.');
				}
			
			
				$heightselect = htmlspecialchars($_POST['heightselect']);
				if ($heightselect == "default") {
					$height = 0;
				}
				else {
					$heightval = htmlspecialchars($_POST['heightval']);
					if ($heightval < 100) {
						throw new RuntimeException('Height value too small (must be >= 100)');
					}
					if ($heightval > 10000) {
						throw new RuntimeException('Height value too large (must be <= 10000)');
					}
					$height = intval($heightval);
				}
				
				$widthselect = htmlspecialchars($_POST['widthselect']);
				if ($widthselect == "default") {
					$width = 0;
				}
				else {
					$widthval = htmlspecialchars($_POST['widthval']);
					if ($widthval < 100) {
						throw new RuntimeException('Width value too small (must be >= 100)');
					}
					if ($widthval > 30000) {
						throw new RuntimeException('Width value too large (must be <= 30000)');
					}
					$width = intval($widthval);
				}
				
				$plotselect = htmlspecialchars($_POST['plotselect']);
				if ($plotselect == "overlay") {
					$overlay = TRUE;
				}
				else {
					$overlay = FALSE;
				}
				
				$result = createfromcsv($_FILES['filename']['tmp_name'], $height, $width, $overlay);
				if ($result !== FALSE) {
					$csvuploaderror = "Success!";
					$pngoutput = '<p><a href="'.$result.'">Right-click -> "Save link as"</a></p>';
				}
				else {
					$csvuploaderror = "Failed.";
					$pngoutput = "";
				}
				
				 

			} 
			catch (RuntimeException $e) {
				$pngoutput = "";
			    $csvuploaderror = $e->getMessage();
			}
	}
}

/*
------------ HTML BUILDERS ------------
*/

function csvfileupload() {
	global $csvuploaderror, $pngoutput;
	echo '
		<form enctype="multipart/form-data" action="'.htmlspecialchars($_SERVER["PHP_SELF"]).'" method="POST">

		    <p>Select .csv file to upload (1MB max file size): </p>			
			<p><input name="filename" type="file" class="inp"></p>
			<p>Height: <br>
				<input type="radio" name="heightselect" value="default" checked> Find the best for me <br>
				<input type="radio" name="heightselect" value="user"> Use this height: <input type="text" name="heightval" class="inp" size="5">
			</p>
			<p>Width: <br>
				<input type="radio" name="widthselect" value="default" checked> 1 pixel per row of data (best lossless compression) <br>
				<input type="radio" name="widthselect" value="user"> Use this width: <input type="text" name="widthval" class="inp" size="5">
			</p>
			<p>Visualisation: <br>
				<input type="radio" name="plotselect" value="overlay" checked> All channels share the same 0V line <br>
				<input type="radio" name="plotselect" value="seperated"> Please seperate channels
			</p>
		    <input name="csvfileupload" type="submit" value="Upload and create" class="subm">
		</form>
		<p>'.$csvuploaderror.'</p>'
		.$pngoutput;
}







?>