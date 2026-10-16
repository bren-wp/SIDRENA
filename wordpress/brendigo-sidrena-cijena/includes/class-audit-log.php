<?php
/** Local administrator-only change log for anchor prices. */
defined( 'ABSPATH' ) || exit;

final class Brendigo_Sidrena_Cijena_Audit_Log {
    const SCHEMA_OPTION = 'brendigo_sidrena_audit_schema';
    const SCHEMA_VERSION = '1';
    private static $instance;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'maybe_upgrade' ), 7 );
        add_filter( 'sidrena_cijena_settings_tabs', array( $this, 'add_tab' ) );
        add_action( 'sidrena_cijena_render_settings_tab', array( $this, 'render_tab' ) );
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'brendigo_sidrena_audit';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            source varchar(12) NOT NULL,
            field_name varchar(40) NOT NULL,
            old_value varchar(191) NOT NULL DEFAULT '',
            new_value varchar(191) NOT NULL DEFAULT '',
            change_source varchar(24) NOT NULL DEFAULT 'system',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            changed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source_entity (source,entity_id,id),
            KEY changed_at (changed_at)
        ) " . $wpdb->get_charset_collate() . ';';
        dbDelta( $sql );
        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
    }

    public function maybe_upgrade() {
        if ( self::SCHEMA_VERSION !== get_option( self::SCHEMA_OPTION, '' ) ) {
            self::install();
        }
    }

    public function record( $source, $id, $field, $old, $new ) {
        $allow = array( 'sidrena_cijena', 'sidrena_datum', 'cijena_redovna', 'cijena_akcijska', 'barkod', 'visibility' );
        $source = 'woo' === $source ? 'woo' : 'manual';
        $id = absint( $id );
        if ( ! $id || ! in_array( $field, $allow, true ) || ! is_scalar( $old ) || ! is_scalar( $new ) ) { return false; }
        $old = substr( (string) $old, 0, 191 );
        $new = substr( (string) $new, 0, 191 );
        if ( $old === $new ) { return false; }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Audit records require a dedicated append-only table.
        return false !== $wpdb->insert( self::table_name(), array(
            'source' => $source, 'entity_id' => $id, 'field_name' => $field,
            'old_value' => $old, 'new_value' => $new,
            'change_source' => defined( 'WP_CLI' ) && WP_CLI ? 'wp-cli' : ( wp_doing_cron() ? 'cron' : ( is_admin() ? 'admin' : 'api/system' ) ),
            'user_id' => get_current_user_id(), 'changed_at' => current_time( 'mysql', true ),
        ), array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ) );
    }

    public function capture_catalog_changes( $old_rows, $new_rows ) {
        $before = array();
        foreach ( $old_rows as $row ) { if ( isset( $row['id'] ) ) { $before[ (string) $row['id'] ] = $row; } }
        $fields = array( 'sidrena_cijena', 'sidrena_datum', 'cijena_redovna', 'cijena_akcijska', 'barkod' );
        foreach ( $new_rows as $row ) {
            $id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
            if ( ! $id ) { continue; }
            $previous = isset( $before[ (string) $id ] ) ? $before[ (string) $id ] : array();
            foreach ( $fields as $field ) {
                $this->record( 'manual', $id, $field, isset( $previous[ $field ] ) ? $previous[ $field ] : '', isset( $row[ $field ] ) ? $row[ $field ] : '' );
            }
        }
    }

    public function add_tab( $tabs ) {
        if ( ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) { return $tabs; }
        $tabs['revizija'] = array( 'label' => __( 'Revizija promjena', 'brendigo-sidrena-cijena' ), 'icon' => 'dashicons-backup' );
        return $tabs;
    }

    public function render_tab( $tab ) {
        if ( 'revizija' !== $tab || ! Brendigo_Sidrena_Cijena_Plugin::current_user_can_manage() ) { return; }
        global $wpdb;
        $table = self::table_name();
        $page = isset( $_GET['audit_page'] ) ? max( 1, absint( wp_unslash( $_GET['audit_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $offset = min( 1000000, ( $page - 1 ) * 30 );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private, uncached administrator audit view must show immediately current records.
        $items = $wpdb->get_results( $wpdb->prepare( 'SELECT entity_id,source,field_name,old_value,new_value,change_source,user_id,changed_at FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, 30, $offset ), ARRAY_A );
        echo '<section class="sidrena-cijena-card"><h2>' . esc_html__( 'Revizijski trag sidrenih cijena', 'brendigo-sidrena-cijena' ) . '</h2>';
        echo '<p>' . esc_html__( 'Lokalni zapis promjena od ove nadogradnje. Dostupan samo ovlaštenim administratorima; podaci se ne šalju vanjskim uslugama. Zapis ne rekonstruira starije promjene.', 'brendigo-sidrena-cijena' ) . '</p>';
        echo '<div class="brendigo-sidrena-table-scroll"><table class="widefat striped"><thead><tr>';
        foreach ( array( 'UTC', 'Izvor', 'ID', 'Polje', 'Prije', 'Poslije', 'Korisnik', 'Način' ) as $heading ) { echo '<th scope="col">' . esc_html( $heading ) . '</th>'; }
        echo '</tr></thead><tbody>';
        if ( ! $items ) { echo '<tr><td colspan="8">' . esc_html__( 'Još nema zabilježenih promjena.', 'brendigo-sidrena-cijena' ) . '</td></tr>'; }
        foreach ( (array) $items as $row ) {
            echo '<tr>';
            foreach ( array( 'changed_at', 'source', 'entity_id', 'field_name', 'old_value', 'new_value', 'user_id', 'change_source' ) as $key ) {
                echo '<td>' . esc_html( (string) $row[ $key ] ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        if ( count( (array) $items ) === 30 ) {
            $url = add_query_arg( array( 'page' => 'brendigo-sidrena-cijena', 'tab' => 'revizija', 'audit_page' => $page + 1 ), admin_url( 'admin.php' ) );
            echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Stariji zapisi', 'brendigo-sidrena-cijena' ) . '</a></p>';
        }
        echo '</section>';
    }
}
