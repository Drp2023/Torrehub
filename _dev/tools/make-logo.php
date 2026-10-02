<?php
// Cut the primary logo out of _dev/brand/torrehub-logo.png (brand sheet) → assets/img/torrehub-logo.{png,webp}.
// Trims to the bounding box of non-white pixels in the top band (primary logo on white), adds a small margin,
// scales to 96px height (3× the 32px header logo). Run: php _dev/tools/make-logo.php
$src = imagecreatefrompng( __DIR__ . '/../brand/torrehub-logo.png' );
$w = imagesx( $src ); $band = 560; // primary logo sits above the black band
$minX = $w; $minY = $band; $maxX = 0; $maxY = 0;
for ( $y = 0; $y < $band; $y++ ) {
	for ( $x = 0; $x < $w; $x++ ) {
		$c = imagecolorat( $src, $x, $y );
		$r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255;
		if ( $r < 235 || $g < 235 || $b < 235 ) {
			$minX = min( $minX, $x ); $maxX = max( $maxX, $x ); $minY = min( $minY, $y ); $maxY = max( $maxY, $y );
		}
	}
}
$pad = 6; $minX -= $pad; $minY -= $pad; $maxX += $pad; $maxY += $pad;
$cw = $maxX - $minX + 1; $ch = $maxY - $minY + 1;
$th = 96; $tw = (int) round( $cw * $th / $ch );
$dst = imagecreatetruecolor( $tw, $th );
imagealphablending( $dst, false ); imagesavealpha( $dst, true );
imagecopyresampled( $dst, $src, 0, 0, $minX, $minY, $tw, $th, $cw, $ch );
// White → transparent (soft threshold) so the logo also sits on tinted surfaces.
for ( $y = 0; $y < $th; $y++ ) {
	for ( $x = 0; $x < $tw; $x++ ) {
		$c = imagecolorat( $dst, $x, $y );
		$r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255;
		$m = min( $r, $g, $b );
		if ( $m > 200 ) {
			$a = (int) round( ( $m - 200 ) / 55 * 127 ); // 0 opaque … 127 transparent
			imagesetpixel( $dst, $x, $y, imagecolorallocatealpha( $dst, $r, $g, $b, min( 127, $a ) ) );
		}
	}
}
$out = __DIR__ . '/../../assets/img/torrehub-logo';
imagepng( $dst, "$out.png", 9 );
imagewebp( $dst, "$out.webp", 90 );
printf( "crop %dx%d at %d,%d -> %dx%d\n", $cw, $ch, $minX, $minY, $tw, $th );
