<?php
/**
 * Loading placeholder card. Hidden from assistive tech; the live region announces results instead.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="th-card th-skeleton" aria-hidden="true">
	<div class="th-card__media"></div>
	<div class="th-card__body">
		<span class="th-skeleton__block th-skeleton__line th-skeleton__line--price"></span>
		<span class="th-skeleton__block th-skeleton__line"></span>
		<span class="th-skeleton__block th-skeleton__line th-skeleton__line--short"></span>
		<span class="th-cluster" style="--th-cluster-gap:6px">
			<span class="th-skeleton__block th-skeleton__chip"></span>
			<span class="th-skeleton__block th-skeleton__chip"></span>
		</span>
	</div>
</div>
