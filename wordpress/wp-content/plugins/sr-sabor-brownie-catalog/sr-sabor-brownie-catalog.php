<?php
/**
 * Plugin Name: Sr Sabor.Brownie Catalog
 * Description: Editable brownie catalog with WhatsApp ordering and no checkout.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: sr-sabor-brownie-catalog
 */

if (!defined('ABSPATH')) {
    exit;
}

function srb_register_content_types() {
    register_post_type('srb_brownie', [
        'labels' => [
            'name' => __('Brownies', 'sr-sabor-brownie-catalog'),
            'singular_name' => __('Brownie', 'sr-sabor-brownie-catalog'),
            'add_new_item' => __('Adicionar brownie', 'sr-sabor-brownie-catalog'),
            'edit_item' => __('Editar brownie', 'sr-sabor-brownie-catalog'),
            'new_item' => __('Novo brownie', 'sr-sabor-brownie-catalog'),
            'view_items' => __('Ver brownies', 'sr-sabor-brownie-catalog'),
            'search_items' => __('Buscar brownies', 'sr-sabor-brownie-catalog'),
            'not_found' => __('Nenhum brownie encontrado.', 'sr-sabor-brownie-catalog'),
            'menu_name' => __('Brownies', 'sr-sabor-brownie-catalog'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-carrot',
        'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);

    register_taxonomy('srb_brownie_line', 'srb_brownie', [
        'labels' => [
            'name' => __('Linhas', 'sr-sabor-brownie-catalog'),
            'singular_name' => __('Linha', 'sr-sabor-brownie-catalog'),
            'add_new_item' => __('Adicionar linha', 'sr-sabor-brownie-catalog'),
            'edit_item' => __('Editar linha', 'sr-sabor-brownie-catalog'),
            'menu_name' => __('Linhas', 'sr-sabor-brownie-catalog'),
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'public' => false,
    ]);
}
add_action('init', 'srb_register_content_types');

function srb_seed_catalog() {
    srb_register_content_types();

    if (get_option('srb_catalog_seeded')) {
        return;
    }

    $existing = get_posts([
        'post_type' => 'srb_brownie',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
    ]);

    if (!$existing) {
        $products = [
            ['Tradicional', 'Chocolate meio amargo, molhadinho por dentro.', 60, '500 g', 'Chocolate meio amargo'],
            ['Especial Café', 'Sabor intenso de café junto com chocolate meio amargo.', 60, '500 g', 'Chocolate meio amargo'],
            ['Nozes', 'Clássico brownie com nozes picadas e crocantes.', 60, '520 g', 'Chocolate meio amargo'],
            ['Oreo', 'Chocolate meio amargo com pedaços de biscoito Oreo.', 60, '500 g', 'Chocolate meio amargo'],
            ['Nutella', 'Brownie recheado com creme de avelã.', 60, '550 g', 'Chocolate meio amargo'],
            ['Double Choco', 'Delicioso brownie com chocolate em gotas.', 60, '500 g', 'Chocolate meio amargo'],
            ['Amêndoas', 'Lâminas de amêndoas torradas e crocantes.', 60, '500 g', 'Chocolate meio amargo'],
            ['Paçoca', 'Chocolate meio amargo com farofa de paçoca.', 60, '520 g', 'Chocolate meio amargo'],
            ['Doce de Leite', 'Uma combinação surpreendente com pedaços de doce de leite.', 60, '550 g', 'Chocolate meio amargo'],
            ['Café com Doce de Leite', 'Brownie com sabor suave de café e pedaços de doce de leite.', 60, '550 g', 'Chocolate meio amargo'],
            ['Blond Nozes', 'Brownie blond com nozes picadas e crocantes.', 60, '520 g', 'Menu Blond'],
            ['Blond Amêndoas', 'Chocolate branco nobre com lâminas de amêndoas torradas.', 60, '500 g', 'Menu Blond'],
            ['Pistache', 'Chocolate branco recheado com ganache de pistache.', 68, '600 g', 'Menu Blond'],
        ];

        foreach ($products as $product) {
            $product_id = wp_insert_post([
                'post_type' => 'srb_brownie',
                'post_status' => 'publish',
                'post_title' => $product[0],
                'post_content' => $product[1],
                'menu_order' => 0,
            ]);

            if (is_wp_error($product_id) || !$product_id) {
                continue;
            }

            update_post_meta($product_id, '_srb_price', (string) $product[2]);
            update_post_meta($product_id, '_srb_weight', $product[3]);

            $term = term_exists($product[4], 'srb_brownie_line');
            if (!$term) {
                $term = wp_insert_term($product[4], 'srb_brownie_line');
            }
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                wp_set_object_terms($product_id, [$term_id], 'srb_brownie_line');
            }
        }
    }

    if (get_option('srb_whatsapp_phone', '') === '') {
        add_option('srb_whatsapp_phone', '5511994472244');
    }
    update_option('srb_catalog_seeded', 1, false);
}
register_activation_hook(__FILE__, 'srb_seed_catalog');

function srb_add_product_fields() {
    add_meta_box(
        'srb_product_details',
        __('Detalhes do brownie', 'sr-sabor-brownie-catalog'),
        'srb_render_product_fields',
        'srb_brownie',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_srb_brownie', 'srb_add_product_fields');

function srb_render_product_fields($post) {
    wp_nonce_field('srb_save_product_fields', 'srb_product_fields_nonce');
    $price = get_post_meta($post->ID, '_srb_price', true);
    $weight = get_post_meta($post->ID, '_srb_weight', true);
    ?>
    <p>
        <label for="srb_price"><strong><?php esc_html_e('Preço (R$)', 'sr-sabor-brownie-catalog'); ?></strong></label><br>
        <input id="srb_price" name="srb_price" type="number" min="0" step="0.01" value="<?php echo esc_attr($price); ?>" class="regular-text">
    </p>
    <p>
        <label for="srb_weight"><strong><?php esc_html_e('Peso', 'sr-sabor-brownie-catalog'); ?></strong></label><br>
        <input id="srb_weight" name="srb_weight" type="text" value="<?php echo esc_attr($weight); ?>" class="regular-text" placeholder="500 g">
    </p>
    <p><?php esc_html_e('Use a imagem destacada para escolher a foto do produto. Produtos em rascunho não aparecem no catálogo.', 'sr-sabor-brownie-catalog'); ?></p>
    <?php
}

function srb_save_product_fields($post_id) {
    if (!isset($_POST['srb_product_fields_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['srb_product_fields_nonce']));
    if (!wp_verify_nonce($nonce, 'srb_save_product_fields')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $price = isset($_POST['srb_price']) ? str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['srb_price']))) : '';
    if ($price !== '' && is_numeric($price) && (float) $price >= 0) {
        update_post_meta($post_id, '_srb_price', number_format((float) $price, 2, '.', ''));
    } else {
        delete_post_meta($post_id, '_srb_price');
    }

    $weight = isset($_POST['srb_weight']) ? sanitize_text_field(wp_unslash($_POST['srb_weight'])) : '';
    update_post_meta($post_id, '_srb_weight', $weight);
}
add_action('save_post_srb_brownie', 'srb_save_product_fields');

function srb_add_product_columns($columns) {
    $columns['srb_price'] = __('Preço', 'sr-sabor-brownie-catalog');
    $columns['srb_weight'] = __('Peso', 'sr-sabor-brownie-catalog');
    return $columns;
}
add_filter('manage_srb_brownie_posts_columns', 'srb_add_product_columns');

function srb_render_product_column($column, $post_id) {
    if ($column === 'srb_price') {
        $price = get_post_meta($post_id, '_srb_price', true);
        echo $price !== '' ? esc_html('R$ ' . number_format_i18n((float) $price, 2)) : '&mdash;';
    }
    if ($column === 'srb_weight') {
        echo esc_html(get_post_meta($post_id, '_srb_weight', true));
    }
}
add_action('manage_srb_brownie_posts_custom_column', 'srb_render_product_column', 10, 2);

function srb_sanitize_phone($phone) {
    return preg_replace('/\D+/', '', (string) $phone);
}

function srb_register_settings() {
    register_setting('srb_catalog_settings', 'srb_whatsapp_phone', [
        'type' => 'string',
        'sanitize_callback' => 'srb_sanitize_phone',
        'default' => '5511994472244',
    ]);
}
add_action('admin_init', 'srb_register_settings');

function srb_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=srb_brownie',
        __('Configurações do catálogo', 'sr-sabor-brownie-catalog'),
        __('WhatsApp', 'sr-sabor-brownie-catalog'),
        'manage_options',
        'srb-catalog-settings',
        'srb_render_settings_page'
    );
}
add_action('admin_menu', 'srb_add_settings_page');

function srb_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Configurações do catálogo', 'sr-sabor-brownie-catalog'); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('srb_catalog_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="srb_whatsapp_phone"><?php esc_html_e('WhatsApp com código do país', 'sr-sabor-brownie-catalog'); ?></label></th>
                    <td>
                        <input id="srb_whatsapp_phone" name="srb_whatsapp_phone" type="tel" class="regular-text" value="<?php echo esc_attr(get_option('srb_whatsapp_phone', '5511994472244')); ?>">
                        <p class="description"><?php esc_html_e('Somente números, incluindo 55 e o DDD. Exemplo: 5511999999999.', 'sr-sabor-brownie-catalog'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function srb_enqueue_catalog_assets() {
    wp_enqueue_style(
        'srb-catalog-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'srb-catalog',
        plugins_url('assets/catalog.css', __FILE__),
        ['srb-catalog-fonts'],
        '1.0.0'
    );
}

function srb_render_catalog_shortcode() {
    srb_enqueue_catalog_assets();

    $lines = get_terms([
        'taxonomy' => 'srb_brownie_line',
        'hide_empty' => true,
        'orderby' => 'name',
    ]);
    if (is_wp_error($lines)) {
        $lines = [];
    }
    $phone = srb_sanitize_phone(get_option('srb_whatsapp_phone', '5511994472244'));
    if ($phone === '') {
        $phone = '5511994472244';
    }

    ob_start();
    ?>
    <div class="srb-site">
        <header class="srb-topbar">
            <a class="srb-brand" href="#srb-inicio">Sr Sabor<span>.Brownie</span></a>
            <nav aria-label="<?php esc_attr_e('Navegação principal', 'sr-sabor-brownie-catalog'); ?>">
                <a href="#srb-cardapio"><?php esc_html_e('Cardápio', 'sr-sabor-brownie-catalog'); ?></a>
                <a class="srb-nav-order" href="<?php echo esc_url('https://wa.me/' . $phone . '?text=' . rawurlencode('Olá! Quero fazer um pedido de brownie.')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Peça pelo WhatsApp', 'sr-sabor-brownie-catalog'); ?></a>
            </nav>
        </header>

        <section class="srb-hero" id="srb-inicio">
            <div class="srb-hero-copy">
                <span class="srb-eyebrow">Sr Sabor.Brownie · feito com carinho</span>
                <h1><?php esc_html_e('Um pedaço de felicidade, do seu jeito.', 'sr-sabor-brownie-catalog'); ?></h1>
                <p><?php esc_html_e('Brownies macios por dentro, cheios de sabor e feitos para transformar qualquer momento em ocasião especial.', 'sr-sabor-brownie-catalog'); ?></p>
                <a class="srb-button" href="#srb-cardapio"><?php esc_html_e('Escolher meu sabor', 'sr-sabor-brownie-catalog'); ?> <span aria-hidden="true">↓</span></a>
                <a class="srb-button srb-button-light" href="<?php echo esc_url('https://wa.me/' . $phone . '?text=' . rawurlencode('Olá! Quero conhecer os brownies Sr Sabor.')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Falar com a gente', 'sr-sabor-brownie-catalog'); ?></a>
            </div>
        </section>

        <section class="srb-intro" aria-label="<?php esc_attr_e('Informações do cardápio', 'sr-sabor-brownie-catalog'); ?>">
            <div>
                <h2><?php esc_html_e('Seu próximo favorito está aqui.', 'sr-sabor-brownie-catalog'); ?></h2>
                <p><?php esc_html_e('Escolha seu sabor e peça direto pelo WhatsApp. Consulte a taxa de entrega com a gente.', 'sr-sabor-brownie-catalog'); ?></p>
            </div>
            <p class="srb-price-note"><?php esc_html_e('Pedidos pelo WhatsApp', 'sr-sabor-brownie-catalog'); ?></p>
        </section>

        <main class="srb-main" id="srb-cardapio">
            <div class="srb-section-heading">
                <div><span class="srb-eyebrow srb-eyebrow-dark"><?php esc_html_e('Feitos para dar água na boca', 'sr-sabor-brownie-catalog'); ?></span><h2><?php esc_html_e('Escolha seu brownie', 'sr-sabor-brownie-catalog'); ?></h2></div>
                <p><?php esc_html_e('Todos os sabores do nosso menu, em um só lugar.', 'sr-sabor-brownie-catalog'); ?></p>
            </div>

            <?php foreach ($lines as $line) : ?>
                <?php
                $products = get_posts([
                    'post_type' => 'srb_brownie',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
                    'tax_query' => [[
                        'taxonomy' => 'srb_brownie_line',
                        'field' => 'term_id',
                        'terms' => $line->term_id,
                    ]],
                ]);
                if (!$products) {
                    continue;
                }
                ?>
                <section class="srb-menu-group" aria-labelledby="srb-line-<?php echo esc_attr($line->term_id); ?>">
                    <div class="srb-group-title"><h3 id="srb-line-<?php echo esc_attr($line->term_id); ?>"><?php echo esc_html($line->name); ?></h3></div>
                    <div class="srb-product-grid">
                        <?php foreach ($products as $product) : ?>
                            <?php
                            $price = get_post_meta($product->ID, '_srb_price', true);
                            $weight = get_post_meta($product->ID, '_srb_weight', true);
                            $message = sprintf('Olá! Quero pedir o brownie %s.', get_the_title($product));
                            $order_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
                            ?>
                            <article class="srb-product">
                                <?php if (has_post_thumbnail($product->ID)) : ?>
                                    <div class="srb-product-photo"><?php echo get_the_post_thumbnail($product->ID, 'large', ['loading' => 'lazy']); ?></div>
                                <?php else : ?>
                                    <div class="srb-product-photo srb-product-placeholder" aria-hidden="true"><span>Sr Sabor.Brownie</span></div>
                                <?php endif; ?>
                                <div class="srb-product-body">
                                    <div class="srb-product-top">
                                        <h4><?php echo esc_html(get_the_title($product)); ?></h4>
                                        <span class="srb-product-price"><?php echo $price !== '' ? esc_html('R$ ' . number_format_i18n((float) $price, 2)) : esc_html__('Consulte', 'sr-sabor-brownie-catalog'); ?></span>
                                    </div>
                                    <div class="srb-product-description"><?php echo wp_kses_post(apply_filters('the_content', $product->post_content)); ?></div>
                                    <div class="srb-product-bottom">
                                        <span class="srb-weight"><?php echo esc_html($weight); ?></span>
                                        <a class="srb-order-link" href="<?php echo esc_url($order_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Pedir este', 'sr-sabor-brownie-catalog'); ?> <span aria-hidden="true">↗</span></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>

        <section class="srb-closing">
            <div>
                <span class="srb-eyebrow"><?php esc_html_e('Um carinho em cada pedaço', 'sr-sabor-brownie-catalog'); ?></span>
                <h2><?php esc_html_e('Tem um sabor chamando por você.', 'sr-sabor-brownie-catalog'); ?></h2>
                <p><?php esc_html_e('Faça seu pedido pelo WhatsApp. A taxa de entrega é consultada de acordo com a sua região.', 'sr-sabor-brownie-catalog'); ?></p>
                <p class="srb-closing-action"><a class="srb-button" href="<?php echo esc_url('https://wa.me/' . $phone . '?text=' . rawurlencode('Olá! Quero fazer um pedido de brownie.')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Quero pedir agora', 'sr-sabor-brownie-catalog'); ?> ↗</a></p>
            </div>
            <div class="srb-care">
                <h3><?php esc_html_e('Para aproveitar cada pedacinho', 'sr-sabor-brownie-catalog'); ?></h3>
                <p><strong><?php esc_html_e('Quer quentinho?', 'sr-sabor-brownie-catalog'); ?></strong> <?php esc_html_e('Aquecer é opcional: coloque no forno preaquecido a 180 °C por 5 minutos.', 'sr-sabor-brownie-catalog'); ?></p>
                <p><strong><?php esc_html_e('Vai guardar?', 'sr-sabor-brownie-catalog'); ?></strong> <?php esc_html_e('Conserve na geladeira por até 8 dias.', 'sr-sabor-brownie-catalog'); ?></p>
                <p><strong><?php esc_html_e('Entrega:', 'sr-sabor-brownie-catalog'); ?></strong> <?php esc_html_e('consulte a taxa pelo WhatsApp.', 'sr-sabor-brownie-catalog'); ?></p>
            </div>
        </section>
        <footer class="srb-footer">
            <span class="srb-brand">Sr Sabor<span>.Brownie</span></span>
            <span><?php esc_html_e('Brownies preparados com carinho e capricho.', 'sr-sabor-brownie-catalog'); ?></span>
            <a href="<?php echo esc_url('https://wa.me/' . $phone); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('WhatsApp · +' . $phone); ?></a>
        </footer>
    </div>
    <?php
    return (string) ob_get_clean();
}
add_shortcode('srb_catalog', 'srb_render_catalog_shortcode');
