<?php
// wp eval-file nexu-apply.php <json>  — ставит молдавские инструкции NEXU (контекст в translation_prompt + custom_instructions_*).
global $wpdb;
$t    = $wpdb->prefix . 'nexu_ai_translator_settings';
$data = json_decode( file_get_contents( $args[0] ), true );
$old  = '/CONTEXT: The content is about WordPress plugins.*?\(shortcode, widget, hook, theme compatibility, plugin, admin, settings, etc\.\)\./s';
$p    = $wpdb->get_var( "SELECT setting_value FROM $t WHERE setting_key='translation_prompt'" );
$np   = preg_replace( $old, $data['context'], $p, 1, $n );
echo "context replaced: $n\n";
if ( $n ) {
	$wpdb->update( $t, array( 'setting_value' => $np ), array( 'setting_key' => 'translation_prompt' ) );
}
foreach ( array( 'custom_instructions', 'custom_instructions_titles', 'custom_instructions_body', 'custom_instructions_terms' ) as $k ) {
	$r = $wpdb->update( $t, array( 'setting_value' => $data[ $k ] ), array( 'setting_key' => $k ) );
	echo "$k: ", false === $r ? 'ERR' : 'ok', ' (', strlen( $data[ $k ] ), ")\n";
}
