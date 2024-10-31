<?php

global $wpdb, $current_user;

?>
<style>
	/* On/off switch: felixhagspiel.de */
	.switch {
		position: relative;
		display: block;
		float: left;
		margin-right: 10px;
	}

	.switch > [type="checkbox"]:checked,
	.switch > [type="checkbox"]:not(:checked) {
		width: 0;
		height: 0;
		display: none;
		opacity: 0; }
	.switch > [type="checkbox"]:checked + label,
	.switch > [type="checkbox"]:not(:checked) + label {
		cursor: pointer;
		display: inline-block;
		margin-right: 7px;
		margin-top: 7px;
		margin-bottom: 7px;
		padding-right: 30px; }
	.switch > [type="checkbox"]:checked + label:after,
	.switch > [type="checkbox"]:not(:checked) + label:after {
		content: "";
		top: 4px;
		right: 10px;
		width: 30px;
		height: 12px;
		position: absolute;
		border-radius: 30px; }
	.switch > [type="checkbox"]:checked + label + .switch-knob,
	.switch > [type="checkbox"]:not(:checked) + label + .switch-knob {
		top: 0;
		width: 20px;
		height: 20px;
		border-radius: 30px;
		display: inline-block;
		position: absolute;
		cursor: pointer;
		pointer-events: none;
		box-shadow: 1px 1px 1px #dddddd;
		-webkit-transition: right .1s ease-in, background-color .1s ease-in;
		-moz-transition: right .1s ease-in, background-color .1s ease-in;
		-o-transition: right .1s ease-in, background-color .1s ease-in;
		-ms-transition: right .1s ease-in, background-color .1s ease-in; }
	.switch > [type="checkbox"]:checked:focus + label:after, .switch > [type="checkbox"]:checked:focus + label + .switch-knob,
	.switch > [type="checkbox"]:not(:checked):focus + label:after,
	.switch > [type="checkbox"]:not(:checked):focus + label + .switch-knob {
		box-shadow: 0 0 6px 0 #6fb5fb; }
	.switch > [type="checkbox"]:checked[disabled] + label, .switch > [type="checkbox"]:checked[disabled] + label:after, .switch > [type="checkbox"]:checked[disabled] + label + .switch-knob,
	.switch > [type="checkbox"]:not(:checked)[disabled] + label,
	.switch > [type="checkbox"]:not(:checked)[disabled] + label:after,
	.switch > [type="checkbox"]:not(:checked)[disabled] + label + .switch-knob {
		cursor: not-allowed;
		opacity: 0.4; }

	.switch > [type="checkbox"]:checked + label:after {
		background-color: #abeab0; }

	.switch > [type="checkbox"]:not(:checked) + label:after {
		background-color: #dddddd; }

	.switch > [type="checkbox"]:checked + label + .switch-knob {
		right: 5px;
		background-color: #46b450; }

	.switch > [type="checkbox"]:not(:checked) + label + .switch-knob {
		right: 25px;
		background-color: #777777; }
	input.spos-large {
		width: 100%;
		max-width: 400px;
	}
	.spos-label {
		display: block;
		font-size: .8em;
	}
	pre {
		padding: 10px;
		background: #fefefe;
	}
	div.scripts, div.styles {
		margin-bottom: 10px;
		padding: 10px 30px;
		background: #fff;
	}
	.file-list {
		max-height: 400px;
		overflow-y: scroll;
	}
	.wrap.spos span.description {
		display: block;
	}
	.wrap.spos hr {
		margin: 25px 0 40px;
	}
	.wrap.spos .lg {
		font-size: 1.25em;
	}
	.col2 a.lg {
		display: inline-block;
	}
	@media screen and (min-width:768px) {
		.row::after {
			content: "";
			clear: both;
			display: table;
		}
		.col1 {
			float: left;
			width: 65%;
		}
		.col2 {
			float: right;
			width: 30%;
			padding: 40px;
			background: #fff;
			border-radius: 8px;
			box-sizing: border-box;
		}
	}
</style>
<div class="wrap spos">
	<h1>Optimize Scripts &amp; Styles</h1>
	<div class="row">
		<div class="col1">
			<form method="post" action="options.php">
			
				<?php
				settings_fields( 'spos_settings_page' );
				do_settings_sections( 'spos_settings_page' );
				submit_button();
				?>
				
			</form>
			
			<hr />

			<h3>Delete Cached Files</h3>
			<p>Cached scripts are stored in the &lt;wp-content&gt;/cache folder. Use the button below to purge the cache. If you've recently changed the ignored files, make sure to clear your cache to generate updated files.</p>
			<form name="spos_clear_cache" action="?page=spos" method="post">
				<?php wp_nonce_field('spos_clear_cache');	?>
				<input type="hidden" name="spos_action" value="flush_all" />
				<?php submit_button( 'Delete Cache', 'delete' ); ?>
			</form>

			<hr />
			
			<h3 id="cache-list">Cached Files</h3>
			<p>The files listed below are what have been generated for your site. Pages or Posts that use the same scripts will use the same optimzied files.</p>
			<p><a href="<?php echo admin_url( 'options-general.php?page=spos#cache-list'); ?>" class="button">Refresh</a></p>
					
			<div class="scripts">
				<h4>Cached Scripts</h4>
				<div class="file-list">
					<?php
					$dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'scripts'. DIRECTORY_SEPARATOR;
					$files = glob( $dir . '*.txt' );
					if ( $files ) {
						foreach ( $files as $filename ) {
							echo '<strong>Hash: ' . str_replace( array($dir,'.txt'), '', $filename ) . '</strong><br />';
							$contents = sanitize_textarea_field( file_get_contents( $filename ) );
							echo '<pre>' . $contents . '</pre><br /><br />';
						}
					} else {
						echo '<p>Cache is empty.</p>';
					}
					?>
				</div>
			</div>
			
			<div class="styles">
				<h4>Cached Styles</h4>
				<div class="file-list">
					<?php
					$dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR;
					$files = glob( $dir . '*.txt' );
					if ( $files ) {
						foreach ( $files as $filename ) {
							echo '<strong>Hash: ' . str_replace( array($dir,'.txt'), '', $filename ) . '</strong><br />';
							$contents = sanitize_textarea_field( file_get_contents( $filename ) );
							echo '<pre>' . $contents . '</pre><br /><br />';
						}				
					} else {
						echo '<p>Cache is empty.</p>';
					}
					?>
				</div>
			</div>

			<p><a href="<?php echo admin_url( 'options-general.php?page=spos#cache-list'); ?>" class="button">Refresh</a></p>
			
		</div>
		<div class="col2">
			<p><strong class="lg">Optimize Scripts &amp; Styles</strong> by <a href="https://www.seismicpixels.com" target="_blank" class="lg">Seismic Pixels</a>.</p><p>Enjoy this plugin? <a href="https://www.seismicpixels.com/coffee.html" target="_blank">Buy me a coffee</a> to say thanks!</p>
		</div>
	</div>	
</div>