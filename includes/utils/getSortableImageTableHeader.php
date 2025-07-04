<?php

function getSortableImageTableHeader( $column_id, $column_title, $current_orderby, $current_order, $selected_folder, $status, $showMiniatures ) {

    $new_order = ( $current_orderby === $column_id && $current_order === 'asc' ) ? 'desc' : 'asc';
    $class = ( $current_orderby === $column_id ) ? 'sorted ' . $current_order : 'sortable ' . $new_order;
    $query_args = array(
        'page'      => 'images',
        'orderby'   => $column_id,
        'order'     => $new_order,
        'status'	=> $status == true ? '0' : '1',
        'miniatures' => $showMiniatures == true ? '0' : '1'
    );
    if ( ! empty( $selected_folder ) ) {
        $query_args['wpil_folder'] = $selected_folder;
    }
    $column_url = add_query_arg( $query_args, admin_url( 'admin.php' ) );
    ?>
    <th scope="col" class="manage-column column-<?php echo esc_attr( $column_id ); ?> <?php echo esc_attr( $class ); ?>">
        <a href="<?php echo esc_url( $column_url ); ?>">
            <span><?php echo esc_html( $column_title ); ?></span>
            <span class="sorting-indicator"></span>
        </a>
    </th>
    <?php
}