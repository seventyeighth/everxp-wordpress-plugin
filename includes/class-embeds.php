<?php
// =====================================
// File: includes/class-embeds.php
// =====================================
if (!defined('ABSPATH')) { exit; }

class EverXP_Embeds {
    private static $table;
    private static $cached = null;

    public static function init(): void {
        global $wpdb;
        self::$table = $wpdb->prefix . 'everxp_embeds';
        add_action('init', [self::class, 'register_public']);
        add_shortcode('everxp', [self::class, 'shortcode']);
    }

    /** Create or upgrade schema (adds `priority` + `conditions`). */
    public static function maybe_create_table(): void {
        global $wpdb;

        if (empty(self::$table)) {
            self::$table = $wpdb->prefix . 'everxp_embeds';
        }
        $table   = self::$table;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create with latest schema
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            type VARCHAR(20) NOT NULL,             -- shortcode | script | html
            payload LONGTEXT NOT NULL,
            placement VARCHAR(191) NOT NULL,       -- manual | before_content | after_content | <hook name>
            scope VARCHAR(50) NOT NULL,            -- back-compat
            priority INT NOT NULL DEFAULT 10,
            conditions LONGTEXT NULL,              -- JSON rules
            loop_every INT NOT NULL DEFAULT 0,     -- items interval (0 = disabled)
            loop_rows INT NOT NULL DEFAULT 0,      -- rows interval (WC archives) (0 = disabled)
            loop_cols_override INT NOT NULL DEFAULT 0, -- columns override (products per row)
            wrap_shop_banner TINYINT(1) NOT NULL DEFAULT 0, -- wrap output like provided banner row
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY placement (placement),
            KEY active (active)
        ) $charset;";
        dbDelta($sql);

        // Host-safe migrations via SHOW COLUMNS
        $ensure_col = function(string $name, string $ddl) use ($wpdb, $table) {
            $col = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $name) );
            if (!$col) { $wpdb->query("ALTER TABLE `$table` ADD $ddl"); }
        };

        // Older installs: placement length
        $placement_info = $wpdb->get_row( $wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'placement'), ARRAY_A );
        if ($placement_info && isset($placement_info['Type'])) {
            if (preg_match('/varchar\((\d+)\)/i', $placement_info['Type'], $m) && (int)$m[1] < 100) {
                $wpdb->query("ALTER TABLE `$table` MODIFY `placement` VARCHAR(191) NOT NULL");
            }
        }

        $ensure_col('priority',            "`priority` INT NOT NULL DEFAULT 10 AFTER `scope`");
        $ensure_col('conditions',          "`conditions` LONGTEXT NULL AFTER `priority`");
        $ensure_col('loop_every',          "`loop_every` INT NOT NULL DEFAULT 0 AFTER `conditions`");
        $ensure_col('loop_rows',           "`loop_rows` INT NOT NULL DEFAULT 0 AFTER `loop_every`");
        $ensure_col('loop_cols_override',  "`loop_cols_override` INT NOT NULL DEFAULT 0 AFTER `loop_rows`");
        $ensure_col('wrap_shop_banner',    "`wrap_shop_banner` TINYINT(1) NOT NULL DEFAULT 0 AFTER `loop_cols_override`");
    }


    private static function ensure_frontend_handles(): void {
        // Front-end STYLE (empty src; we attach inline CSS to this handle)
        if (!wp_style_is('everxp-frontend-style', 'registered')) {
            wp_register_style('everxp-frontend-style', false, [], null);
        }
        if (!wp_style_is('everxp-frontend-style', 'enqueued')) {
            wp_enqueue_style('everxp-frontend-style');
        }

        // Front-end SCRIPT (container handle; strategy=defer since WP 6.3)
        if (!wp_script_is('everxp-frontend-script', 'registered')) {
            wp_register_script(
                'everxp-frontend-script',
                false,                        // no file; we attach inline runtime
                [],                           // keep deps light; add 'jquery' if needed
                null,
                [ 'in_footer' => true, 'strategy' => 'defer' ] // async/defer ready
            );
        }
        if (!wp_script_is('everxp-frontend-script', 'enqueued')) {
            wp_enqueue_script('everxp-frontend-script');
        }
    }

    private static function enqueue_frontend_css(array $embeds): void {
        $css = '';
        foreach ($embeds as $e) {
            if (empty($e['active'])) { continue; }
            if (empty($e['conditions'])) { continue; }
            $c = json_decode((string)$e['conditions'], true);
            if (!is_array($c)) { continue; }
            if (!empty($c['custom_css'])) {
                $css .= "\n/* everxp #{$e['id']} */\n" . str_replace('</style>', '', (string)$c['custom_css']) . "\n";
            }
        }
        if ($css !== '') {
            add_action('wp_enqueue_scripts', function() use ($css) {
                EverXP_Embeds::ensure_frontend_handles();
                wp_add_inline_style('everxp-frontend-style', $css);
            }, 20); // after theme styles are enqueued
        }
    }


    /** Runtime: attach to action hooks; wrap content placements via the_content. */
    public static function register_public(): void {
        if (is_admin()) { return; }

        add_action('wp', function () {
            self::$cached = self::get_active_embeds();

            // Shop-grid per-item hooks we will handle via JS instead of PHP hooks.
            $js_shop_hooks = [
                'woocommerce_shop_loop',
                'woocommerce_before_shop_loop_item',
                'woocommerce_after_shop_loop_item',
            ];

            $js_payloads = [];
            $php_hooks   = [];

            self::enqueue_frontend_css( self::$cached );

            foreach (self::$cached as $e) {
                $placement = (string)($e['placement'] ?? '');
                if (!$placement || $placement === 'manual') { continue; }

                // Route shop-grid per-item placements to JS
                if (in_array($placement, $js_shop_hooks, true)) {
                    if (self::passes_scope($e) && self::passes_conditions($e)) {
                        $loop = self::get_loop_settings($e);
                        if ($loop['enabled']) {
                            $js_payloads[] = [
                                'id'         => (int)$e['id'],
                                'html'       => self::render($e), // pre-rendered HTML
                                'mode'       => $loop['mode'],    // fixed|random
                                'every'      => (int)$loop['every_items'],
                                'perRow'     => (int)$loop['per_row'],
                                'minRows'    => (int)$loop['min_rows'],
                                'maxRows'    => (int)$loop['max_rows'],
                            ];
                        }
                    }
                    continue;
                }

                // Everything else stays with PHP hooks
                $php_hooks[] = $e;
            }

            // Register non-grid (regular) placements on PHP hooks
            foreach ($php_hooks as $e) {
                $placement = $e['placement'] ?? '';
                if (!$placement) { continue; }
                $priority = isset($e['priority']) ? max(1, min(999, (int)$e['priority'])) : 10;

                add_action($placement, function () use ($e) {
                    if (!EverXP_Embeds::passes_scope($e) || !EverXP_Embeds::passes_conditions($e)) { return; }
                    echo EverXP_Embeds::render($e);
                }, $priority);
            }

            // Enqueue the JS inserter when we have shop-grid embeds to place
            if (!empty($js_payloads)) {
                add_action('wp_enqueue_scripts', function () use ($js_payloads) {
                    EverXP_Embeds::enqueue_js_inserter($js_payloads);
                });
            }
        });

        // Content filter for above/below article stays intact
        add_filter('the_content', function ($content) {
            $before = ''; $after = '';
            foreach (self::by_placement('before_content') as $e) {
                if (self::passes_scope($e) && self::passes_conditions($e)) { $before .= self::render($e); }
            }
            foreach (self::by_placement('after_content') as $e) {
                if (self::passes_scope($e) && self::passes_conditions($e)) { $after  .= self::render($e); }
            }
            return ($before || $after) ? ($before . $content . $after) : $content;
        }, 99);
    }



    private static function enqueue_js_inserter(array $embeds): void {
        // Prepare data for JS
        $payload = [];
        foreach ($embeds as $e) {
            $payload[] = [
                'id'      => (int)$e['id'],
                'html'    => (string)$e['html'],
                'mode'    => (string)$e['mode'],
                'every'   => (int)$e['every'],
                'perRow'  => (int)$e['perRow'],
                'minRows' => (int)$e['minRows'],
                'maxRows' => (int)$e['maxRows'],
                'css'     => isset($e['css']) ? (string)$e['css'] : '',
            ];
        }
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Ensure our front-end containers are ready
        add_action('wp_enqueue_scripts', function () use ($json) {
            EverXP_Embeds::ensure_frontend_handles();

            // 3.1) Inline DATA first (safe space; no <script> echoes)
            wp_add_inline_script(
                'everxp-frontend-script',
                'window.EVERXP_EMBEDS = ' . $json . ';',
                'before'
            );

            // 3.2) Inline RUNTIME (latest from your working JS inserter)
            $runtime = <<<'JS'
    (function(){
    "use strict";
    var EMBEDS = (window.EVERXP_EMBEDS||[]);

    function qsa(sel, ctx){return Array.prototype.slice.call((ctx||document).querySelectorAll(sel));}
    function isEl(n){return n && n.nodeType===1;}
    function ensureStyle(embed){
      if (!embed.css || !embed.css.trim()) return;
      var id = 'everxp-css-' + String(embed.id);
      if (document.getElementById(id)) return;
      var s = document.createElement('style');
      s.id = id;
      s.appendChild(document.createTextNode(embed.css));
      (document.head || document.documentElement).appendChild(s);
    }
    function runScripts(container){
      var scripts = container.querySelectorAll('script');
      scripts.forEach(function(oldS){
        var s = document.createElement('script');
        for (var i=0;i<oldS.attributes.length;i++){ var a=oldS.attributes[i]; s.setAttribute(a.name, a.value); }
        if (oldS.text && oldS.text.trim()) s.text = oldS.text;
        oldS.parentNode.replaceChild(s, oldS);
      });
    }
    function buildWrapper(grid){
      var isList = /^(UL|OL)$/i.test(grid.tagName);
      var tag = isList ? 'LI' : 'DIV';
      var el  = document.createElement(tag);
      el.className = 'everxp-banner-insert';
      el.style.cssText = [
        'grid-column:1 / -1','width:100%','flex:0 0 100%','float:none','clear:both','list-style:none',
        (isList ? 'display:list-item' : 'display:block'),'margin:30px 0'
      ].join(';');
      if (grid.classList.contains('products') || grid.closest('.elementor-woocommerce-products')){
        el.classList.add('product');
      }
      return el;
    }
    function getItems(grid){
      var items = qsa(':scope > li.wc-block-product, :scope > li.wc-block-grid__product, :scope > li.product, :scope > li', grid)
        .filter(function(n){ return isEl(n) && !n.classList.contains('everxp-banner-insert'); });
      return items;
    }
    function fixedPositions(count, every){ var out=[]; if (every<1) return out; for (var i=every;i<=count;i+=every) out.push(i); return out; }
    function randomPositions(count, minRows, maxRows, perRow){
      var out=[], i=0;
      minRows=Math.max(1, minRows|0); maxRows=Math.max(minRows, maxRows|0); perRow=Math.max(1, perRow|0);
      while(true){ var rows=Math.floor(Math.random()*(maxRows-minRows+1))+minRows; var step=Math.max(1, rows*perRow); i+=step; if(i>count) break; out.push(i); }
      return out;
    }
    function injectAtPositions(grid, items, positions, embed){
      ensureStyle(embed);
      positions.forEach(function(pos){
        var item = items[pos-1]; if (!item) return;
        var next = item.nextElementSibling;
        if (next && next.classList && next.classList.contains('everxp-banner-insert') && next.getAttribute('data-embed-id')===String(embed.id)) return;
        var wrap = buildWrapper(grid);
        wrap.setAttribute('data-embed-id', String(embed.id));
        wrap.setAttribute('data-pos', String(pos));
        wrap.innerHTML = '<div class="shop-banner-row" style="width:100%; text-align:center; display:flex; justify-content:center; flex-wrap:wrap;"><div class="shop-banner-content">'+ embed.html +'</div></div>';
        item.parentNode.insertBefore(wrap, item.nextSibling);
        runScripts(wrap);
      });
    }
    function processGrid(grid){
      EMBEDS.forEach(function(embed){
        var items = getItems(grid); if (!items.length) return;
        var positions = (embed.mode==='fixed') ? fixedPositions(items.length, Math.max(1, embed.every|0))
                                               : randomPositions(items.length, embed.minRows|0, embed.maxRows|0, embed.perRow|0);
        injectAtPositions(grid, items, positions, embed);
      });
    }
    function observeGrid(grid){
      var obs = new MutationObserver(function(){ processGrid(grid); });
      obs.observe(grid, {childList:true});
      processGrid(grid);
    }
    function findGrids(){
      var c=[];
      c=c.concat(qsa('ul.wc-block-product-template'));
      c=c.concat(qsa('ul.wc-block-grid__products'));
      c=c.concat(qsa('ul.products'));
      c=c.concat(qsa('.wp-block-woocommerce-product-template ul.wc-block-product-template'));
      c=c.concat(qsa('.wp-block-woocommerce-all-products ul, .wp-block-woocommerce-product-collection ul'));
      var seen=new Set(), u=[]; c.forEach(function(g){ if (g && !seen.has(g)){ seen.add(g); u.push(g);} });
      return u;
    }
    function init(){ findGrids().forEach(observeGrid); }
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.addEventListener('load', init);
    })();
    JS;
            wp_add_inline_script('everxp-frontend-script', $runtime, 'after');
        }, 20);
    }


    /**
     * Inject a banner <li> inside Woo Blocks grids (block themes).
     * Works on HTML rendered by woocommerce/all-products & woocommerce/product-collection.
     */
    private static function inject_into_wc_blocks_grid(string $grid_html, array $e): string {
        $loop = self::get_loop_settings($e);
        if (!$loop['enabled']) { return $grid_html; }

        // Split by each product LI in Woo Blocks grids
        $parts = preg_split('/(<li\b[^>"]*class="[^"]*wc-block-grid__product[^"]*"[^>]*>.*?<\/li>)/si', $grid_html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!$parts || count($parts) < 2) {
            return $grid_html;
        }

        $result = '';
        $item_index = 0;

        // For random rows, compute first trigger
        $next_at = null;
        if ($loop['mode'] === 'random') {
            $rows = mt_rand($loop['min_rows'], $loop['max_rows']);
            $next_at = max(1, (int)$rows * max(1, (int)$loop['per_row']));
        }

        foreach ($parts as $chunk) {
            $result .= $chunk;

            // Is this chunk a product item LI?
            if (stripos($chunk, 'wc-block-grid__product') !== false) {
                $item_index++;

                $should_insert = false;

                if ($loop['mode'] === 'fixed') {
                    $every = max(1, (int)$loop['every_items']);
                    $should_insert = ($item_index % $every === 0);
                } else {
                    if ($next_at !== null && $item_index >= $next_at) {
                        $should_insert = true;
                        $rows = mt_rand($loop['min_rows'], $loop['max_rows']);
                        $next_at = $item_index + max(1, (int)$rows * max(1, (int)$loop['per_row']));
                    }
                }

                if ($should_insert) {
                    $result .= self::maybe_wrap_banner(self::render($e), 'woo_blocks_li'); // force proper wrapper for blocks
                }
            }
        }

        return $result;
    }


    private static function detect_default_wrapper(): string {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            return 'woo_blocks_li'; // TT4 and other block themes
        }
        $theme = wp_get_theme();
        $slug  = strtolower($theme->get('TextDomain') ?: $theme->get('Template') ?: $theme->get('Name') ?: '');
        if (strpos($slug, 'hello-elementor') !== false || $slug === 'hello' || $slug === 'helloelementor') {
            return 'hello_elementor';
        }
        return 'woo_generic_li';
    }

    /** TRUE when running a block theme. */
    private static function is_block_theme_active(): bool {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }

    /** Parse loop settings and normalize wrapper choice. */
    private static function get_loop_settings(array $e): array {
        $out = [
            'enabled'     => false,
            'mode'        => 'fixed',
            'every_items' => 0,
            'per_row'     => 2,
            'min_rows'    => 2,
            'max_rows'    => 4,
            'wrapper'     => 'auto',
        ];
        $c = [];
        if (!empty($e['conditions'])) {
            $decoded = json_decode((string)$e['conditions'], true);
            if (is_array($decoded)) { $c = $decoded; }
        }
        if (empty($c['loop']) && isset($c['loop_mode'])) {
            $c['loop'] = [
                'mode'        => $c['loop_mode'] ?? 'fixed',
                'every_items' => (int)($c['loop_every_items'] ?? 0),
                'per_row'     => (int)($c['loop_per_row'] ?? 2),
                'min_rows'    => (int)($c['loop_min_rows'] ?? 2),
                'max_rows'    => (int)($c['loop_max_rows'] ?? (int)($c['loop_min_rows'] ?? 2)),
                'wrapper'     => $c['loop_wrapper'] ?? 'auto',
            ];
        }
        if (!empty($c['loop']) && is_array($c['loop'])) {
            $l = $c['loop'];
            $out['mode']        = in_array($l['mode'] ?? 'fixed', ['fixed','random'], true) ? $l['mode'] : 'fixed';
            $out['every_items'] = max(0, (int)($l['every_items'] ?? 0));
            $out['per_row']     = max(1, (int)($l['per_row'] ?? 2));
            $out['min_rows']    = max(1, (int)($l['min_rows'] ?? 2));
            $out['max_rows']    = max($out['min_rows'], (int)($l['max_rows'] ?? $out['min_rows']));
            $out['wrapper']     = (string)($l['wrapper'] ?? 'auto');
        }
        $out['enabled'] = ($out['mode'] === 'fixed' && $out['every_items'] > 0)
            || ($out['mode'] === 'random' && $out['min_rows'] > 0 && $out['max_rows'] >= $out['min_rows']);
        return $out;
    }

    /** Helper: optional banner wrapper matching your snippet. */
    private static function maybe_wrap_banner(string $html, string $wrapper): string {
        // Empty content => nothing to wrap
        if ($html === '' || $html === null) {
            return '';
        }

        // Normalize wrapper aliases
        $wrapper = (string)$wrapper;
        if ($wrapper === '' || $wrapper === 'woo_banner') {
            $wrapper = 'auto';
        }

        // Helpers
        $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();

        // Detect if we're likely inside a shop grid context (best-effort)
        $in_shop_context = false;
        if (function_exists('is_shop') && function_exists('is_product_taxonomy')) {
            $in_shop_context = is_shop() || is_product_taxonomy() || (function_exists('is_product_category') && is_product_category()) || (function_exists('is_product_tag') && is_product_tag());
        }
        // Also treat "we're in a shop loop" actions as grid context
        $in_shop_context = $in_shop_context
            || did_action('woocommerce_before_shop_loop')
            || did_action('woocommerce_shop_loop')
            || did_action('woocommerce_after_shop_loop');

        // Auto-select wrapper by theme if requested
        if ($wrapper === 'auto') {
            $wrapper = $is_block_theme ? 'woo_blocks_li' : 'hello_elementor';
        }

        // If explicitly "none", return raw HTML (for manual placements)
        if ($wrapper === 'none') {
            return $html;
        }

        // Build shared inline styles
        $shared = implode(' ', [
            'grid-column:1 / -1;', // span all columns in CSS grid layouts
            'width:100%;',
            'flex:0 0 100%;',      // full row in flex layouts
            'float:none;',         // neutralize floats
            'clear:both;',
            'list-style:none;',
            'margin:30px 0;',
        ]);

        // Decide wrapper element for the current context
        $should_use_li = $in_shop_context || in_array($wrapper, ['woo_blocks_li','hello_elementor','woo_product_li','woo_generic_li'], true);

        // ---- GRID WRAPPERS (LI) ----
        if ($should_use_li) {
            // Choose class set per wrapper + theme, but never use Woo Blocks item class to avoid column rules
            $li_classes = ['everxp-banner-insert'];
            if ($wrapper === 'hello_elementor' || $wrapper === 'woo_product_li') {
                // Some classic themes expect `.product` LI for spacing
                $li_classes[] = 'product';
            }
            // For Woo Blocks, DO NOT add wc-block-grid__product; it breaks the grid.
            // For generic, leave as plain LI.

            // display:list-item preserves semantics inside UL/OL
            $li_style = $shared . ' display:list-item;';
            $li = '<li class="' . esc_attr(implode(' ', $li_classes)) . '" style="' . esc_attr($li_style) . '">'
                .    '<div class="shop-banner-row" style="width:100%; text-align:center; display:flex; justify-content:center; flex-wrap:wrap;">'
                .      '<div class="shop-banner-content">' . $html . '</div>'
                .    '</div>'
                .  '</li>';
            return $li;
        }

        // ---- NON-GRID WRAPPER (DIV) ----
        $div_style = $shared . ' display:block;';
        return '<div class="everxp-banner-insert" style="' . esc_attr($div_style) . '">'
             .   '<div class="shop-banner-row" style="width:100%; text-align:center; display:flex; justify-content:center; flex-wrap:wrap;">'
             .     '<div class="shop-banner-content">' . $html . '</div>'
             .   '</div>'
             . '</div>';
    }


    /** Shortcode: [everxp id="123"] */
    public static function shortcode($atts): string {
        $atts = shortcode_atts(['id' => 0], $atts, 'everxp');
        $id = absint($atts['id']);
        if (!$id) { return ''; }
        $row = self::get($id);
        if (!$row || !intval($row['active'])) { return ''; }
        if (!self::passes_scope($row) || !self::passes_conditions($row)) { return ''; }
        return self::render($row);
    }

    /** Admin block embedded in settings page (includes upgraded "Where should it appear?"). */
    public static function render_admin_block(): void {
        if (!current_user_can('manage_options')) {
            echo '<div class="error"><p>You do not have permission to manage embeds.</p></div>';
            return;
        }

        global $wpdb;
        if (empty(self::$table)) { self::$table = $wpdb->prefix . 'everxp_embeds'; }
        self::maybe_create_table();

        // CREATE
        if (isset($_POST['everxp_add_embed'])) {
            check_admin_referer('everxp_save_embed', 'everxp_nonce');
            self::handle_save_or_update('create'); // redirects on success
        }

        // UPDATE
        if (isset($_POST['everxp_update_embed'])) {
            check_admin_referer('everxp_update_embed', 'everxp_nonce_edit');
            self::handle_save_or_update('update'); // redirects on success
        }

        // ACTIONS
        if (isset($_GET['everxp_action'], $_GET['id'])) {
            $id = absint($_GET['id']);
            $action = sanitize_text_field($_GET['everxp_action']);
            $nonce = $_GET['_wpnonce'] ?? '';
            if (wp_verify_nonce($nonce, 'everxp_action_' . $id)) {
                if ($action === 'toggle') {
                    $row = self::get($id);
                    if ($row) {
                        $wpdb->update(self::$table, ['active' => intval($row['active']) ? 0 : 1], ['id' => $id], ['%d'], ['%d']);
                    }
                    $clean = remove_query_arg(['everxp_action','id','_wpnonce','updated','message']);
                    wp_safe_redirect($clean); exit;
                } elseif ($action === 'delete') {
                    $wpdb->delete(self::$table, ['id' => $id], ['%d']);
                    $clean = remove_query_arg(['everxp_action','id','_wpnonce','updated','message']);
                    wp_safe_redirect($clean); exit;
                } /* edit falls through */
            } else {
                echo '<div class="error"><p>Action not authorized.</p></div>';
            }
        }

        // EDIT FORM
        $editing = (isset($_GET['everxp_action'], $_GET['id']) && $_GET['everxp_action'] === 'edit' && ($row = self::get(absint($_GET['id']))));
        if ($editing) {
            $row = self::get(absint($_GET['id']));
            if (!$row) {
                echo '<div class="error"><p>Embed not found.</p></div>';
            } else {
                echo '<div class="wrap"><h2>Edit Embed</h2>';
                self::render_form($row, 'update');
                echo '</div>';
            }
        }

        // LIST
        $embeds = $wpdb->get_results("SELECT * FROM " . self::$table . " ORDER BY id DESC", ARRAY_A);

        echo '<div class="wrap">';
        echo '<h2 class="wp-heading-inline">Embeds</h2>';
        echo '<hr class="wp-header-end" />';

        echo '<table class="widefat fixed striped"><thead><tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Type</th>
                <th scope="col">Placement</th>
                <th scope="col">Hook</th>
                <th scope="col">Priority</th>
                <th scope="col">Loop</th>         <!-- NEW -->
                <th scope="col">Rules</th>
                <th scope="col">Shortcode</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr></thead><tbody>';

        if ($embeds) {
            foreach ($embeds as $e) {
                $toggle_url = wp_nonce_url(add_query_arg(['everxp_action' => 'toggle', 'id' => $e['id']]), 'everxp_action_' . $e['id']);
                $delete_url = wp_nonce_url(add_query_arg(['everxp_action' => 'delete', 'id' => $e['id']]), 'everxp_action_' . $e['id']);
                $edit_url   = wp_nonce_url(add_query_arg(['everxp_action' => 'edit',   'id' => $e['id']]),   'everxp_action_' . $e['id']);

                $placement = $e['placement'];
                $is_content = in_array($placement, ['before_content','after_content','manual'], true);
                $rules_summary = self::summarize_conditions($e['conditions'] ?? '');

                // Show original saved shortcode if type=shortcode; always show manual embed separately
                if ($e['type'] === 'shortcode') {
                    $shortcode_cell = '<code>' . esc_html($e['payload']) . '</code><br><span class="description">Manual: <code>[everxp id="' . esc_html($e['id']) . '"]</code></span>';
                } else {
                    $shortcode_cell = '<code>[everxp id="' . esc_html($e['id']) . '"]</code>';
                }

                // NEW: Loop column (read from saved JSON)
                $loop = self::get_loop_settings($e);
                $loop_text = ($loop['mode'] === 'random')
                    ? sprintf('mode: random · rows %d–%d ·/row %d · wrapper: %s',
                              (int)$loop['min_rows'], (int)$loop['max_rows'], (int)$loop['per_row'], esc_html($loop['wrapper']))
                    : sprintf('mode: fixed · every %d items · wrapper: %s',
                              (int)$loop['every_items'], esc_html($loop['wrapper']));

                echo '<tr>';
                echo '<td>' . esc_html($e['id']) . '</td>';
                echo '<td><strong>' . esc_html($e['name']) . '</strong></td>';
                echo '<td>' . esc_html($e['type']) . '</td>';
                echo '<td>' . esc_html(self::human_placement($placement)) . '</td>';
                echo '<td>' . ($is_content ? '-' : esc_html($placement)) . '</td>';
                echo '<td>' . ($is_content ? '-' : esc_html((string)($e['priority'] ?? 10))) . '</td>';
                echo '<td>' . esc_html($loop_text) . '</td>';  // NEW
                echo '<td>' . esc_html($rules_summary ?: '—') . '</td>';
                echo '<td>' . $shortcode_cell . '</td>';
                echo '<td>' . (intval($e['active']) ? 'Active' : 'Inactive') . '</td>';
                echo '<td>
                        <span class="row-actions">
                            <a href="' . esc_url($edit_url) . '">Edit</a> |
                            <a href="' . esc_url($toggle_url) . '">' . (intval($e['active']) ? 'Deactivate' : 'Activate') . '</a> |
                            <a href="' . esc_url($delete_url) . '" class="submitdelete" onclick="return confirm(\'Delete this embed?\')">Delete</a>
                        </span>
                      </td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="11">No embeds yet.</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:24px;">Add New Embed</h2>';
        self::render_form(null, 'create');

        echo '</div>'; // .wrap
    }

/**
 * Handle create/update submit (shared).
 * $mode: 'create' | 'update'
 */
    private static function handle_save_or_update(string $mode): void {
        global $wpdb;
        $is_update = ($mode === 'update');
        $id = $is_update ? absint($_POST['id'] ?? 0) : 0;

        $name      = sanitize_text_field($_POST['name'] ?? '');
        $type      = sanitize_text_field($_POST['type'] ?? 'shortcode');
        $placement = (string)($_POST['placement'] ?? 'manual');
        $scope     = 'sitewide';
        $priority  = isset($_POST['priority']) ? (int)$_POST['priority'] : 10;
        $priority  = max(1, min(999, $priority));

        if ($placement === 'custom_hook') {
            $placement = (string)($_POST['custom_hook_name'] ?? '');
        }
        $placement = preg_replace('/[^A-Za-z0-9_\/]+/', '', $placement);
        $placement = substr($placement, 0, 180);

        $payload = '';
        if ($type === 'shortcode') {
            $payload = trim((string)wp_unslash($_POST['payload_shortcode'] ?? ''));
        } elseif ($type === 'script') {
            $payload = esc_url_raw(trim((string)($_POST['payload_script'] ?? '')));
        } elseif ($type === 'html') {
            $payload = (string)wp_unslash($_POST['payload_html'] ?? '');
        }

        $post_types = array_map('sanitize_key', array_filter((array)($_POST['cond_post_types'] ?? [])));
        $locations  = array_map('sanitize_key', array_filter((array)($_POST['cond_locations'] ?? [])));
        $wc_pages   = array_map('sanitize_key', array_filter((array)($_POST['cond_wc_pages'] ?? [])));

        $inc_cats = self::sanitize_csv_terms($_POST['cond_include_categories'] ?? '');
        $exc_cats = self::sanitize_csv_terms($_POST['cond_exclude_categories'] ?? '');
        $inc_tags = self::sanitize_csv_terms($_POST['cond_include_tags'] ?? '');
        $exc_tags = self::sanitize_csv_terms($_POST['cond_exclude_tags'] ?? '');

        $inc_ids = self::sanitize_csv_ids($_POST['cond_include_ids'] ?? '');
        $exc_ids = self::sanitize_csv_ids($_POST['cond_exclude_ids'] ?? '');

        $user_state = sanitize_text_field($_POST['cond_user_state'] ?? 'any');
        $roles_csv  = sanitize_text_field($_POST['cond_roles'] ?? '');
        $roles      = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $roles_csv))));

        $devices   = array_map('sanitize_key', array_filter((array)($_POST['cond_devices'] ?? [])));
        $langs_csv = sanitize_text_field($_POST['cond_languages'] ?? '');
        $languages = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $langs_csv))));

        // Loop options
        $loop_mode   = sanitize_text_field($_POST['loop_mode'] ?? 'fixed');
        $every_items = max(0, (int)($_POST['loop_every_items'] ?? 0));
        $per_row     = max(1, (int)($_POST['loop_per_row'] ?? 2));
        $min_rows    = max(1, (int)($_POST['loop_min_rows'] ?? 2));
        $max_rows    = max($min_rows, (int)($_POST['loop_max_rows'] ?? $min_rows));
        $wrapper_in  = sanitize_text_field($_POST['loop_wrapper'] ?? 'auto');

        $allowed_wrappers = ['none','hello_elementor','woo_product_li','woo_generic_li','woo_blocks_li','auto','woo_banner'];
        if (!in_array($wrapper_in, $allowed_wrappers, true)) {
            $wrapper_in = 'auto';
        }
        if ($wrapper_in === 'auto' || $wrapper_in === 'woo_banner') {
            $wrapper_in = self::detect_default_wrapper();
        }

        $conditions = [
            'post_types' => $post_types,
            'locations'  => $locations,
            'wc_pages'   => $wc_pages,
            'include_categories' => $inc_cats,
            'exclude_categories' => $exc_cats,
            'include_tags' => $inc_tags,
            'exclude_tags' => $exc_tags,
            'include_ids' => $inc_ids,
            'exclude_ids' => $exc_ids,
            'users'   => ['logged' => $user_state, 'roles' => $roles],
            'devices' => $devices,
            'languages' => $languages,
            'loop' => [
                'mode'        => in_array($loop_mode, ['fixed','random'], true) ? $loop_mode : 'fixed',
                'every_items' => $every_items,
                'per_row'     => $per_row,
                'min_rows'    => $min_rows,
                'max_rows'    => $max_rows,
                'wrapper'     => $wrapper_in,
            ],
        ];
        $conditions_json = wp_json_encode($conditions);

        if (!$name || !$type || !$placement || !$payload) {
            echo '<div class="error"><p>Please fill all required fields.</p></div>';
            return;
        }

        $data = [
            'name'       => $name,
            'type'       => $type,
            'payload'    => $payload,
            'placement'  => $placement,
            'scope'      => 'sitewide',
            'priority'   => $priority,
            'conditions' => $conditions_json,
            'active'     => 1,
        ];
        $formats = ['%s','%s','%s','%s','%s','%d','%s','%d'];

        if ($is_update) {
            $ok = $wpdb->update(self::$table, $data, ['id' => $id], $formats, ['%d']);
            if ($ok === false) {
                $err = $wpdb->last_error ? esc_html($wpdb->last_error) : 'Unknown database error.';
                echo '<div class="error"><p>Failed to update. DB said: <code>' . $err . '</code></p></div>';
                return;
            }
            $clean = remove_query_arg(['everxp_action','id','_wpnonce','everxp_update_embed','everxp_add_embed','updated','message']);
            wp_safe_redirect($clean); exit;
        } else {
            $ok = $wpdb->insert(self::$table, $data, $formats);
            if (!$ok) {
                $err = $wpdb->last_error ? esc_html($wpdb->last_error) : 'Unknown database error.';
                echo '<div class="error"><p>Failed to add embed. DB said: <code>' . $err . '</code></p></div>';
                return;
            }
            $clean = remove_query_arg(['everxp_action','id','_wpnonce','everxp_update_embed','everxp_add_embed','updated','message']);
            wp_safe_redirect($clean); exit;
        }
    }

/**
 * Render the Add/Edit form (shared).
 * $row = null => Add, else Edit.
 * $mode: 'create'|'update'
 */
    private static function render_form(?array $row, string $mode = 'create'): void {
        $is_edit = ($mode === 'update');
        $id      = $row['id'] ?? 0;
        $name    = $row['name'] ?? '';
        $type    = $row['type'] ?? 'shortcode';
        $payload = $row['payload'] ?? '';
        $placement = $row['placement'] ?? 'before_content';
        $priority  = isset($row['priority']) ? (int)$row['priority'] : 10;

        // Decode conditions
        $conditions = [];
        if (!empty($row['conditions'])) {
            $decoded = json_decode((string)$row['conditions'], true);
            if (is_array($decoded)) { $conditions = $decoded; }
        }
        $loop = (array)($conditions['loop'] ?? []);
        $wrapper = $loop['wrapper'] ?? self::detect_default_wrapper();

        $wrapper_options = [
            'auto'            => 'Auto (detect by theme)',
            'woo_blocks_li'   => 'Woo Blocks (Twenty Twenty-Four / block themes)',
            'hello_elementor' => 'Hello Elementor',
            'woo_product_li'  => 'WooCommerce — Product LI (full-width)',
            'woo_generic_li'  => 'WooCommerce — Generic LI (full-width)',
            'none'            => 'None',
        ];
        $post_types = (array)($conditions['post_types'] ?? []);
        $locations  = (array)($conditions['locations']  ?? []);
        $wc_pages   = (array)($conditions['wc_pages']   ?? []);

        $inc_cats = (array)($conditions['include_categories'] ?? []);
        $exc_cats = (array)($conditions['exclude_categories'] ?? []);
        $inc_tags = (array)($conditions['include_tags'] ?? []);
        $exc_tags = (array)($conditions['exclude_tags'] ?? []);

        $inc_ids  = (array)($conditions['include_ids'] ?? []);
        $exc_ids  = (array)($conditions['exclude_ids'] ?? []);

        $users      = (array)($conditions['users'] ?? []);
        $user_state = $users['logged'] ?? 'any';
        $roles      = (array)($users['roles'] ?? []);

        $devices   = (array)($conditions['devices'] ?? []);
        $languages = (array)($conditions['languages'] ?? []);

        $loop = (array)($conditions['loop'] ?? []);
        $loop_mode   = $loop['mode']        ?? 'fixed';
        $every_items = (int)($loop['every_items'] ?? 0);
        $per_row     = (int)($loop['per_row']     ?? 2);
        $min_rows    = (int)($loop['min_rows']    ?? 2);
        $max_rows    = (int)($loop['max_rows']    ?? max($min_rows, 2));
        $wrapper     = $loop['wrapper']     ?? 'none';

        // Known placements; anything else is treated as custom
        $known = [
            'before_content','after_content','wp_head','wp_footer','manual',
            'wp_body_open','loop_start','loop_end','get_sidebar','comments_template',
            'woocommerce_before_main_content','woocommerce_after_main_content','woocommerce_before_shop_loop','woocommerce_after_shop_loop',
            'woocommerce_before_shop_loop_item','woocommerce_after_shop_loop_item','woocommerce_shop_loop',
            'woocommerce_before_single_product','woocommerce_single_product_summary','woocommerce_after_single_product',
            'woocommerce_before_cart','woocommerce_cart_contents','woocommerce_after_cart',
            'woocommerce_before_checkout_form','woocommerce_checkout_before_customer_details','woocommerce_checkout_after_customer_details','woocommerce_after_checkout_form',
            'woocommerce_thankyou','woocommerce_before_account_navigation','woocommerce_account_dashboard','woocommerce_after_account_navigation',
            'elementor/theme/before_do_header','elementor/theme/after_do_header','elementor/theme/before_do_footer','elementor/theme/after_do_footer',
        ];
        $custom_value = (!in_array($placement, $known, true) && $placement !== 'custom_hook') ? $placement : '';

        echo '<form method="post">';
        if ($is_edit) {
            wp_nonce_field('everxp_update_embed', 'everxp_nonce_edit');
            echo '<input type="hidden" name="id" value="' . esc_attr((string)$id) . '">';
        } else {
            wp_nonce_field('everxp_save_embed', 'everxp_nonce');
        }

        echo '<table class="form-table" role="presentation"><tbody>';

        // Name
        echo '<tr><th scope="row"><label for="everxp-name">Name</label></th>
                <td><input name="name" id="everxp-name" type="text" class="regular-text" required value="' . esc_attr($name) . '" placeholder="e.g., Vendor Widget / Newsletter Form"></td></tr>';

        // Type
        echo '<tr><th scope="row"><label for="everxp-type">Type</label></th>
                <td><select name="type" id="everxp-type" class="regular-text">
                        <option value="shortcode" ' . selected($type,'shortcode',false) . '>Shortcode</option>
                        <option value="script" '    . selected($type,'script',false)    . '>Script URL</option>
                        <option value="html" '      . selected($type,'html',false)      . '>HTML snippet</option>
                    </select>
                    <p class="description">Shortcode: <code>[vendor_widget id="123"]</code> · Script URL: a JS file URL · HTML: full embed code.</p>
                </td></tr>';

        // Payloads
        echo '<tr class="payload payload-shortcode" ' . ($type==='shortcode'?'':'style="display:none"') . '>
                <th scope="row"><label for="payload_shortcode">Shortcode</label></th>
                <td><input name="payload_shortcode" id="payload_shortcode" type="text" class="regular-text" value="' . esc_attr($type==='shortcode'?$payload:'') . '" placeholder=\'[vendor_widget id="123"]\'></td></tr>';

        echo '<tr class="payload payload-script" ' . ($type==='script'?'':'style="display:none"') . '>
                <th scope="row"><label for="payload_script">Script URL</label></th>
                <td><input name="payload_script" id="payload_script" type="url" class="regular-text" value="' . esc_attr($type==='script'?$payload:'') . '" placeholder="https://cdn.example.com/widget.js"></td></tr>';

        echo '<tr class="payload payload-html" ' . ($type==='html'?'':'style="display:none"') . '>
                <th scope="row"><label for="payload_html">HTML / JS embed</label></th>
                <td><textarea name="payload_html" id="payload_html" class="large-text code" rows="6" placeholder="<div id=\'x\'></div><script>/* vendor embed */</script>">' . ($type==='html'?esc_textarea($payload):'') . '</textarea></td></tr>';

        // ===== PLACEMENT (FULL) =====
        echo '<tr><th scope="row"><label for="everxp-placement">Placement</label></th><td>';
        echo '<select name="placement" id="everxp-placement" class="regular-text">';
        echo '<optgroup label="Simple placements">';
        echo '<option value="before_content" ' . selected($placement,'before_content',false) . '>Above the article</option>';
        echo '<option value="after_content" '  . selected($placement,'after_content',false)  . '>Below the article</option>';
        echo '<option value="wp_head" '        . selected($placement,'wp_head',false)        . '>Site header (all pages)</option>';
        echo '<option value="wp_footer" '      . selected($placement,'wp_footer',false)      . '>Site footer (all pages)</option>';
        echo '<option value="manual" '         . selected($placement,'manual',false)         . '>I will place it manually (use shortcode)</option>';
        echo '</optgroup>';

        echo '<optgroup label="WordPress core hooks">';
        echo '<option value="wp_body_open" '   . selected($placement,'wp_body_open',false)   . '>wp_body_open (top of &lt;body&gt;)</option>';
        echo '<option value="loop_start" '     . selected($placement,'loop_start',false)     . '>loop_start</option>';
        echo '<option value="loop_end" '       . selected($placement,'loop_end',false)       . '>loop_end</option>';
        echo '<option value="get_sidebar" '    . selected($placement,'get_sidebar',false)    . '>get_sidebar</option>';
        echo '<option value="comments_template" ' . selected($placement,'comments_template',false) . '>comments_template</option>';
        echo '</optgroup>';

        echo '<optgroup label="WooCommerce — Shop & Archives">';
        echo '<option value="woocommerce_before_main_content" ' . selected($placement,'woocommerce_before_main_content',false) . '>woocommerce_before_main_content</option>';
        echo '<option value="woocommerce_after_main_content" '  . selected($placement,'woocommerce_after_main_content',false)  . '>woocommerce_after_main_content</option>';
        echo '<option value="woocommerce_before_shop_loop" '    . selected($placement,'woocommerce_before_shop_loop',false)    . '>woocommerce_before_shop_loop</option>';
        echo '<option value="woocommerce_after_shop_loop" '     . selected($placement,'woocommerce_after_shop_loop',false)     . '>woocommerce_after_shop_loop</option>';
        echo '<option value="woocommerce_before_shop_loop_item" '. selected($placement,'woocommerce_before_shop_loop_item',false). '>woocommerce_before_shop_loop_item</option>';
        echo '<option value="woocommerce_after_shop_loop_item" ' . selected($placement,'woocommerce_after_shop_loop_item',false) . '>woocommerce_after_shop_loop_item</option>';
        echo '<option value="woocommerce_shop_loop" '            . selected($placement,'woocommerce_shop_loop',false)            . '>woocommerce_shop_loop (each product)</option>';
        echo '</optgroup>';

        echo '<optgroup label="WooCommerce — Single Product">';
        echo '<option value="woocommerce_before_single_product" ' . selected($placement,'woocommerce_before_single_product',false) . '>woocommerce_before_single_product</option>';
        echo '<option value="woocommerce_single_product_summary" ' . selected($placement,'woocommerce_single_product_summary',false) . '>woocommerce_single_product_summary</option>';
        echo '<option value="woocommerce_after_single_product" '   . selected($placement,'woocommerce_after_single_product',false)   . '>woocommerce_after_single_product</option>';
        echo '</optgroup>';

        echo '<optgroup label="WooCommerce — Cart / Checkout / Account">';
        echo '<option value="woocommerce_before_cart" ' . selected($placement,'woocommerce_before_cart',false) . '>woocommerce_before_cart</option>';
        echo '<option value="woocommerce_cart_contents" ' . selected($placement,'woocommerce_cart_contents',false) . '>woocommerce_cart_contents</option>';
        echo '<option value="woocommerce_after_cart" ' . selected($placement,'woocommerce_after_cart',false) . '>woocommerce_after_cart</option>';
        echo '<option value="woocommerce_before_checkout_form" ' . selected($placement,'woocommerce_before_checkout_form',false) . '>woocommerce_before_checkout_form</option>';
        echo '<option value="woocommerce_checkout_before_customer_details" ' . selected($placement,'woocommerce_checkout_before_customer_details',false) . '>woocommerce_checkout_before_customer_details</option>';
        echo '<option value="woocommerce_checkout_after_customer_details" ' . selected($placement,'woocommerce_checkout_after_customer_details',false) . '>woocommerce_checkout_after_customer_details</option>';
        echo '<option value="woocommerce_after_checkout_form" ' . selected($placement,'woocommerce_after_checkout_form',false) . '>woocommerce_after_checkout_form</option>';
        echo '<option value="woocommerce_thankyou" ' . selected($placement,'woocommerce_thankyou',false) . '>woocommerce_thankyou</option>';
        echo '<option value="woocommerce_before_account_navigation" ' . selected($placement,'woocommerce_before_account_navigation',false) . '>woocommerce_before_account_navigation</option>';
        echo '<option value="woocommerce_account_dashboard" ' . selected($placement,'woocommerce_account_dashboard',false) . '>woocommerce_account_dashboard</option>';
        echo '<option value="woocommerce_after_account_navigation" ' . selected($placement,'woocommerce_after_account_navigation',false) . '>woocommerce_after_account_navigation</option>';
        echo '</optgroup>';

        echo '<optgroup label="Elementor Theme Builder">';
        echo '<option value="elementor/theme/before_do_header" ' . selected($placement,'elementor/theme/before_do_header',false) . '>elementor/theme/before_do_header</option>';
        echo '<option value="elementor/theme/after_do_header" ' . selected($placement,'elementor/theme/after_do_header',false) . '>elementor/theme/after_do_header</option>';
        echo '<option value="elementor/theme/before_do_footer" ' . selected($placement,'elementor/theme/before_do_footer',false) . '>elementor/theme/before_do_footer</option>';
        echo '<option value="elementor/theme/after_do_footer" ' . selected($placement,'elementor/theme/after_do_footer',false) . '>elementor/theme/after_do_footer</option>';
        echo '</optgroup>';

        echo '<optgroup label="Custom">';
        echo '<option value="custom_hook">— Custom action hook name… —</option>';
        echo '</optgroup>';
        echo '</select>';
        echo '<p class="description">Choose where the embed should be output.</p>';

        // Custom hook / Priority
        $is_custom = (!in_array($placement, $known, true) && $placement !== 'custom_hook');
        echo '<div id="everxp-custom-hook-wrap" style="margin-top:8px;' . (($placement==='custom_hook'||$is_custom)?'':'display:none;') . '">
                <label for="everxp-custom-hook">Custom hook:</label>
                <input type="text" id="everxp-custom-hook" name="custom_hook_name" class="regular-text" value="' . esc_attr($custom_value) . '" placeholder="my_theme_after_header">
              </div>';

        echo '<div id="everxp-priority-wrap" style="margin-top:8px;">
                <label for="everxp-priority">Priority:</label>
                <input type="number" id="everxp-priority" name="priority" min="1" max="999" value="' . esc_attr((string)$priority) . '" style="width:100px;">
              </div>';

        echo '</td></tr>';

        // ---------------- Display Conditions ----------------
        echo '<tr><th scope="row">Where should it appear?</th><td>';

        // Content Types
        $pts = get_post_types(['public' => true], 'objects');
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Content types</strong></legend>';
        foreach ($pts as $pt) {
            $checked = in_array($pt->name, $post_types, true);
            echo '<label style="margin-right:12px;"><input type="checkbox" name="cond_post_types[]" value="' . esc_attr($pt->name) . '" ' . checked($checked, true, false) . '> ' . esc_html($pt->labels->singular_name) . '</label>';
        }
        echo '<p class="description">Leave empty to allow all public post types.</p></fieldset>';

        // Page context
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Page context</strong></legend>';
        $locs = [
            'singular'  => 'Single post/page',
            'archive'   => 'Archive (category/tag/author/search etc.)',
            'front'     => 'Front page',
            'blog_home' => 'Blog home (posts page)',
        ];
        foreach ($locs as $k => $label) {
            $checked = in_array($k, $locations, true);
            echo '<label style="margin-right:12px;"><input type="checkbox" name="cond_locations[]" value="' . esc_attr($k) . '" ' . checked($checked,true,false) . '> ' . esc_html($label) . '</label>';
        }
        echo '</fieldset>';

        // WooCommerce pages
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>WooCommerce pages</strong></legend>';
        $wc = [
            'shop'     => 'Shop',
            'product'  => 'Single product',
            'cart'     => 'Cart',
            'checkout' => 'Checkout',
            'account'  => 'My account',
            'thankyou' => 'Order received (Thank you)',
        ];
        foreach ($wc as $k => $label) {
            $checked = in_array($k, $wc_pages, true);
            echo '<label style="margin-right:12px;"><input type="checkbox" name="cond_wc_pages[]" value="' . esc_attr($k) . '" ' . checked($checked,true,false) . '> ' . esc_html($label) . '</label>';
        }
        echo '<p class="description">These apply only if WooCommerce is active.</p></fieldset>';

        // Categories/Tags include/exclude
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Categories / Tags</strong></legend>';
        echo '<label>Include categories (IDs or slugs, comma-separated): <input type="text" name="cond_include_categories" class="regular-text" value="' . esc_attr(implode(',', $inc_cats)) . '" placeholder="news, 12, updates"></label><br>';
        echo '<label>Exclude categories (IDs or slugs, comma-separated): <input type="text" name="cond_exclude_categories" class="regular-text" value="' . esc_attr(implode(',', $exc_cats)) . '" placeholder="sponsored, 34"></label><br>';
        echo '<label>Include tags (IDs or slugs, comma-separated): <input type="text" name="cond_include_tags" class="regular-text" value="' . esc_attr(implode(',', $inc_tags)) . '" placeholder="summer, 77"></label><br>';
        echo '<label>Exclude tags (IDs or slugs, comma-separated): <input type="text" name="cond_exclude_tags" class="regular-text" value="' . esc_attr(implode(',', $exc_tags)) . '" placeholder="beta"></label>';
        echo '<p class="description">Only checked on single posts.</p></fieldset>';

        // IDs include/exclude
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Specific items</strong></legend>';
        echo '<label>Include post/page IDs: <input type="text" name="cond_include_ids" class="regular-text" value="' . esc_attr(implode(',', $inc_ids)) . '" placeholder="10, 25, 99"></label><br>';
        echo '<label>Exclude post/page IDs: <input type="text" name="cond_exclude_ids" class="regular-text" value="' . esc_attr(implode(',', $exc_ids)) . '" placeholder="101, 202"></label>';
        echo '</fieldset>';

        // Users
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Users</strong></legend>';
        echo '<label><select name="cond_user_state">
                <option value="any" ' . selected($user_state,'any',false) . '>Anyone</option>
                <option value="in" '  . selected($user_state,'in',false)  . '>Logged-in users only</option>
                <option value="out" ' . selected($user_state,'out',false) . '>Logged-out users only</option>
              </select></label> ';
        echo '<label style="margin-left:12px;">Limit to roles (slugs, comma-separated): <input type="text" name="cond_roles" class="regular-text" value="' . esc_attr(implode(',', $roles)) . '" placeholder="subscriber, customer"></label>';
        echo '</fieldset>';

        // Devices
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Devices</strong></legend>';
        echo '<label style="margin-right:12px;"><input type="checkbox" name="cond_devices[]" value="desktop" ' . checked(in_array('desktop',$devices,true),true,false) . '> Desktop</label>';
        echo '<label><input type="checkbox" name="cond_devices[]" value="mobile" ' . checked(in_array('mobile',$devices,true),true,false) . '> Mobile</label>';
        echo '<p class="description">Mobile detection uses WordPress’s <code>wp_is_mobile()</code>.</p></fieldset>';

        // Languages
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Language</strong></legend>';
        echo '<label>Only show for languages (codes, comma-separated): <input type="text" name="cond_languages" class="regular-text" value="' . esc_attr(implode(',', $languages)) . '" placeholder="en, he, fr"></label>';
        echo '<p class="description">WPML uses ICL_LANGUAGE_CODE; Polylang uses <code>pll_current_language()</code>.</p></fieldset>';

        echo '</td></tr>';

        // -------- Loop options (for repeating hooks) --------
        echo '<tr><th scope="row">Loop options (for repeating hooks)</th><td>';
        echo '<fieldset style="margin-bottom:10px;"><legend><strong>Mode</strong></legend>';
        echo '<label style="margin-right:12px;"><input type="radio" name="loop_mode" value="fixed" ' . checked($loop_mode,'fixed',false) . '> Fixed (every N items)</label>';
        echo '<label><input type="radio" name="loop_mode" value="random" ' . checked($loop_mode,'random',false) . '> Random (rows range)</label>';
        echo '</fieldset>';

        echo '<div id="everxp-loop-fixed" ' . ($loop_mode==='fixed'?'':'style="display:none"') . '>
                <label>Every N items: <input type="number" name="loop_every_items" min="0" value="' . esc_attr((string)$every_items) . '" style="width:120px;"></label>
                <p class="description">Use with <code>woocommerce_shop_loop</code> or other per-item hooks.</p>
              </div>';

        echo '<div id="everxp-loop-random" ' . ($loop_mode==='random'?'':'style="display:none"') . '>
                <label>Products per row: <input type="number" name="loop_per_row" min="1" value="' . esc_attr((string)$per_row) . '" style="width:120px;"></label><br>
                <label>Random rows range: min <input type="number" name="loop_min_rows" min="1" value="' . esc_attr((string)$min_rows) . '" style="width:90px;">
                       max <input type="number" name="loop_max_rows" min="1" value="' . esc_attr((string)$max_rows) . '" style="width:90px;"></label>
                <p class="description">Banner appears after a random number of rows, then re-randomizes. Interval = rows × products per row.</p>
              </div>';

        $wrapper_options = [
            'auto'            => 'Auto (detect by theme)',
            'woo_blocks_li'   => 'Woo Blocks (Twenty Twenty-Four / block themes)',
            'hello_elementor' => 'Hello Elementor',
            'woo_product_li'  => 'WooCommerce — Product LI (full-width)',
            'woo_generic_li'  => 'WooCommerce — Generic LI (full-width)',
            'none'            => 'None',
        ];

        echo '<div style="margin-top:10px;">
                <label>Wrapper style:
                    <select name="loop_wrapper">';
        foreach ($wrapper_options as $val => $label) {
            $selected = selected($wrapper, $val, false);
            echo '<option value="' . esc_attr($val) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '      </select>
                </label>
                <p class="description">Tip: on Twenty Twenty-Four use “Woo Blocks”, on Hello use “Hello Elementor”.</p>
              </div>';

        echo '</td></tr>';

        echo '</tbody></table>';

        echo '<p class="submit">';
        if ($is_edit) {
            echo '<button type="submit" class="button button-primary" name="everxp_update_embed" value="1">Update Embed</button> ';
            echo '<a href="' . esc_url(remove_query_arg(['everxp_action','id','_wpnonce'])) . '" class="button">Cancel</a>';
        } else {
            echo '<button type="submit" class="button button-primary" name="everxp_add_embed" value="1">Add Embed</button>';
        }
        echo '</p>';

        // Minimal JS to keep WP look; no CSS changes
        echo '<script>(function(){
            const typeSel=document.getElementById("everxp-type");
            const rows={shortcode:document.querySelector(".payload-shortcode"),script:document.querySelector(".payload-script"),html:document.querySelector(".payload-html")};
            function refreshPayload(){Object.keys(rows).forEach(k=>rows[k].style.display="none");const v=typeSel.value;if(rows[v])rows[v].style.display="";}
            if(typeSel){typeSel.addEventListener("change",refreshPayload);refreshPayload();}

            const placeSel=document.getElementById("everxp-placement");
            const customWrap=document.getElementById("everxp-custom-hook-wrap");
            const customInput=document.getElementById("everxp-custom-hook");
            const known=new Set(' . json_encode($known) . ');
            function refreshPlacement(){
                const v=placeSel.value;
                const isCustom=(v==="custom_hook")||(v && !known.has(v));
                customWrap.style.display=isCustom?"":"none";
                if(!isCustom && customInput){customInput.value="";}
            }
            if(placeSel){placeSel.addEventListener("change",refreshPlacement);refreshPlacement();}

            const modeFixed=document.querySelector("input[name=loop_mode][value=fixed]");
            const modeRandom=document.querySelector("input[name=loop_mode][value=random]");
            const boxFixed=document.getElementById("everxp-loop-fixed");
            const boxRandom=document.getElementById("everxp-loop-random");
            function refreshLoop(){
                if(modeFixed && modeFixed.checked){boxFixed.style.display="";boxRandom.style.display="none";}
                if(modeRandom && modeRandom.checked){boxFixed.style.display="none";boxRandom.style.display="";}
            }
            if(modeFixed && modeRandom){modeFixed.addEventListener("change",refreshLoop);modeRandom.addEventListener("change",refreshLoop);refreshLoop();}
        })();</script>';

        echo '</form>';
    }

    // ---------- helpers ----------
    private static function get_active_embeds(): array {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . self::$table . " WHERE active = 1 ORDER BY id ASC", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
    private static function by_placement(string $placement): array {
        if (!is_array(self::$cached)) { return []; }
        return array_values(array_filter(self::$cached, fn($e) => ($e['placement'] ?? '') === $placement));
    }
    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::$table . " WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    private static function passes_scope(array $e): bool {
        $s = $e['scope'] ?? 'sitewide';
        if ($s === 'sitewide') { return true; }
        if (is_singular('post') && $s === 'posts') { return true; }
        if (is_singular('page') && $s === 'pages') { return true; }
        return true; // keep permissive; detailed rules handled by conditions
    }

    private static function passes_conditions(array $e): bool {
        $json = $e['conditions'] ?? '';
        if (!$json) { return true; }
        $c = json_decode($json, true);
        if (!is_array($c)) { return true; }

        // Locations
        $locs = $c['locations'] ?? [];
        if ($locs && !self::match_locations($locs)) { return false; }

        // Post types
        $pts = $c['post_types'] ?? [];
        if ($pts) {
            if (is_singular()) {
                $pt = get_post_type(get_queried_object_id());
                if (!$pt || !in_array($pt, $pts, true)) { return false; }
            } else {
                // On archives, allow if current post type (if determinable) matches any chosen.
                // If cannot determine, do not block.
            }
        }

        // WooCommerce
        $wc = $c['wc_pages'] ?? [];
        if ($wc && function_exists('is_woocommerce')) {
            $ok = true;
            // If any wc context selected, require at least one to match
            $ok = (
                (in_array('shop',$wc,true) && function_exists('is_shop') && is_shop()) ||
                (in_array('product',$wc,true) && function_exists('is_product') && is_product()) ||
                (in_array('cart',$wc,true) && function_exists('is_cart') && is_cart()) ||
                (in_array('checkout',$wc,true) && function_exists('is_checkout') && is_checkout()) ||
                (in_array('account',$wc,true) && function_exists('is_account_page') && is_account_page()) ||
                (in_array('thankyou',$wc,true) && function_exists('is_order_received_page') && is_order_received_page())
            );
            if (!$ok) { return false; }
        } elseif ($wc && !function_exists('is_woocommerce')) {
            return false; // requested WC context but WC not available
        }

        // Include/Exclude IDs
        $qid = get_queried_object_id();
        $inc_ids = $c['include_ids'] ?? [];
        if ($inc_ids && $qid && !in_array((int)$qid, array_map('intval',$inc_ids), true)) { return false; }
        $exc_ids = $c['exclude_ids'] ?? [];
        if ($exc_ids && $qid && in_array((int)$qid, array_map('intval',$exc_ids), true)) { return false; }

        // Categories/Tags (singular posts/pages)
        if (is_singular()) {
            if (!empty($c['include_categories']) && !self::post_in_terms($qid, 'category', $c['include_categories'])) { return false; }
            if (!empty($c['exclude_categories']) && self::post_in_terms($qid, 'category', $c['exclude_categories'])) { return false; }
            if (!empty($c['include_tags']) && !self::post_in_terms($qid, 'post_tag', $c['include_tags'])) { return false; }
            if (!empty($c['exclude_tags']) && self::post_in_terms($qid, 'post_tag', $c['exclude_tags'])) { return false; }
        }

        // Users
        $users = $c['users'] ?? [];
        $logged = $users['logged'] ?? 'any';
        if ($logged === 'in' && !is_user_logged_in()) { return false; }
        if ($logged === 'out' && is_user_logged_in()) { return false; }
        $roles = $users['roles'] ?? [];
        if ($roles) {
            if (!is_user_logged_in()) { return false; }
            $u = wp_get_current_user();
            if (!array_intersect($roles, (array)$u->roles)) { return false; }
        }

        // Devices
        $devices = $c['devices'] ?? [];
        if ($devices) {
            $is_mobile = wp_is_mobile();
            if ($is_mobile && !in_array('mobile', $devices, true)) { return false; }
            if (!$is_mobile && !in_array('desktop', $devices, true)) { return false; }
        }

        // Languages
        $langs = $c['languages'] ?? [];
        if ($langs) {
            $cur = null;
            if (defined('ICL_LANGUAGE_CODE')) { $cur = ICL_LANGUAGE_CODE; }
            elseif (function_exists('pll_current_language')) { $cur = pll_current_language('slug'); }
            if (!$cur || !in_array(sanitize_key($cur), array_map('sanitize_key',$langs), true)) { return false; }
        }

        return true;
    }

    private static function match_locations(array $locs): bool {
        // If none selected, consider pass.
        if (!$locs) { return true; }
        $map = [
            'singular'  => is_singular(),
            'archive'   => is_archive(),
            'front'     => is_front_page(),
            'blog_home' => is_home(),
        ];
        foreach ($locs as $k) {
            if (isset($map[$k]) && $map[$k]) { return true; }
        }
        return false;
    }

    private static function post_in_terms(int $post_id, string $taxonomy, array $terms): bool {
        if (!$post_id) { return false; }
        // Accept IDs or slugs
        $ids = []; $slugs = [];
        foreach ($terms as $t) { if (is_numeric($t)) { $ids[] = (int)$t; } else { $slugs[] = sanitize_title($t); } }
        if ($ids && has_term($ids, $taxonomy, $post_id)) { return true; }
        if ($slugs && has_term($slugs, $taxonomy, $post_id)) { return true; }
        return false;
    }

    private static function sanitize_csv_terms(string $csv): array {
        $items = array_filter(array_map('trim', explode(',', $csv)));
        return array_values(array_map(function($v){ return is_numeric($v) ? (int)$v : sanitize_title($v); }, $items));
    }
    private static function sanitize_csv_ids(string $csv): array {
        $items = array_filter(array_map('trim', explode(',', $csv)));
        return array_values(array_map('intval', array_filter($items, 'is_numeric')));
    }

    private static function render(array $e): string {
        $type = $e['type']; $payload = (string)$e['payload'];
        if ($type === 'shortcode') { return do_shortcode($payload); }
        if ($type === 'script') {
            $src = esc_url(trim($payload)); if (!$src) { return ''; }
            return '<script src="' . $src . '" async></script>';
        }
        if ($type === 'html') { return $payload; }
        return '';
    }

    private static function human_placement(string $p): string {
        $map = [
            'before_content' => 'Above the article',
            'after_content'  => 'Below the article',
            'manual'         => 'Manual (use shortcode)',
            'wp_head'        => 'Site header (wp_head)',
            'wp_footer'      => 'Site footer (wp_footer)',
            'wp_body_open'   => 'Top of body (wp_body_open)',
        ];
        return $map[$p] ?? $p;
    }

    private static function summarize_conditions(string $json): string {
        if (!$json) { return ''; }
        $c = json_decode($json, true);
        if (!is_array($c)) { return ''; }
        $bits = [];
        if (!empty($c['post_types'])) { $bits[] = 'Types: ' . implode(',', (array)$c['post_types']); }
        if (!empty($c['locations'])) { $bits[] = 'Loc: ' . implode(',', (array)$c['locations']); }
        if (!empty($c['wc_pages'])) { $bits[] = 'WC: ' . implode(',', (array)$c['wc_pages']); }
        if (!empty($c['devices'])) { $bits[] = 'Dev: ' . implode(',', (array)$c['devices']); }
        if (!empty($c['users']['logged']) && $c['users']['logged'] !== 'any') { $bits[] = 'Users: ' . $c['users']['logged']; }
        if (!empty($c['users']['roles'])) { $bits[] = 'Roles: ' . implode(',', (array)$c['users']['roles']); }
        if (!empty($c['include_categories']) || !empty($c['exclude_categories'])) { $bits[] = 'Cats inc/exc'; }
        if (!empty($c['include_tags']) || !empty($c['exclude_tags'])) { $bits[] = 'Tags inc/exc'; }
        if (!empty($c['include_ids']) || !empty($c['exclude_ids'])) { $bits[] = 'IDs inc/exc'; }
        if (!empty($c['languages'])) { $bits[] = 'Lang: ' . implode(',', (array)$c['languages']); }
        return implode(' | ', $bits);
    }
}
