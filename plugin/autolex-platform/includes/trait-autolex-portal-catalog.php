<?php
/** Autolex Portal rendering component. */
if (!defined('ABSPATH')) { exit; }

trait Autolex_Portal_Catalog_Trait
{
    private function render_catalogue()
    {
        $filters = $this->filters_from_query();
        $data    = $this->query_vehicles($filters);
        $facets  = $this->get_facets((string) $filters['make']);

        ob_start();
        ?>
        <main class="alx3-catalog" id="autolex-catalog">
            <header class="alx3-catalog-hero">
                <div>
                    <span class="alx3-kicker"><b>EU / EEA</b> SZŰRHETŐ KATALÓGUS</span>
                    <h1>Találd meg a <em>pontos</em> autóváltozatot.</h1>
                    <p>Márka, modell, hajtás, évjárat, teljesítmény, motorkód és adatminőség szerint. A találatokon külön látszik a forrás- és ellenőrzési állapot.</p>
                </div>
                <aside><strong data-result-count><?php echo esc_html(number_format_i18n($data['total'])); ?></strong><span>találat az adatbázisban</span><small><?php echo esc_html(number_format_i18n(count($facets['makes']))); ?> listázott márka</small></aside>
            </header>

            <div class="alx3-catalog-layout">
                <aside class="alx3-filters" data-filter-panel>
                    <header><div><span>SZŰRŐRENDSZER</span><h2>Autóadatok</h2></div><button type="button" data-filter-close aria-label="Szűrők bezárása">×</button></header>
                    <form action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" data-autolex-filter-form>
                        <label class="alx3-field alx3-field--wide"><span>Szabad keresés</span><input type="search" name="q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="Márka, modell, motor, motorkód"></label>
                        <label class="alx3-field"><span>Márka</span><select name="make" data-make-select><option value="">Összes márka</option><?php echo $this->options($facets['makes'], $filters['make']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                        <label class="alx3-field"><span>Modell</span><select name="model" data-model-select><option value="">Összes modell</option><?php echo $this->options($facets['models'], $filters['model']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                        <label class="alx3-field"><span>Üzemanyag</span><select name="fuel"><option value="">Minden hajtás</option><?php echo $this->options($facets['fuels'], $filters['fuel']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                        <label class="alx3-field"><span>Motorkód</span><input type="text" name="engine_code" value="<?php echo esc_attr($filters['engine_code']); ?>" placeholder="pl. N47D20"></label>
                        <div class="alx3-field-group"><span>Évjárat</span><label><small>ettől</small><input type="number" name="year_min" min="1950" max="<?php echo esc_attr((string) ((int) gmdate('Y') + 2)); ?>" value="<?php echo esc_attr($filters['year_min']); ?>" placeholder="<?php echo esc_attr((string) ($facets['ranges']['year_min'] ?: 2000)); ?>"></label><label><small>eddig</small><input type="number" name="year_max" min="1950" max="<?php echo esc_attr((string) ((int) gmdate('Y') + 2)); ?>" value="<?php echo esc_attr($filters['year_max']); ?>" placeholder="<?php echo esc_attr((string) ($facets['ranges']['year_max'] ?: gmdate('Y'))); ?>"></label></div>
                        <div class="alx3-field-group"><span>Teljesítmény (LE)</span><label><small>minimum</small><input type="number" name="power_min" min="1" max="2500" value="<?php echo esc_attr($filters['power_min']); ?>" placeholder="50"></label><label><small>maximum</small><input type="number" name="power_max" min="1" max="2500" value="<?php echo esc_attr($filters['power_max']); ?>" placeholder="800"></label></div>
                        <fieldset class="alx3-grade-filter"><legend>Adatminőség</legend><?php foreach (array('' => 'Mindegy', 'A' => 'A – részletes', 'B' => 'B – műszaki alap', 'C' => 'C – alaprekord') as $value => $label) : ?><label><input type="radio" name="grade" value="<?php echo esc_attr($value); ?>" <?php checked($filters['grade'], $value); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></fieldset>
                        <button class="alx3-filter-submit" type="submit">Szűrés alkalmazása</button>
                        <a class="alx3-filter-reset" href="<?php echo esc_url(home_url('/autok/')); ?>">Minden szűrő törlése</a>
                    </form>
                </aside>

                <section class="alx3-results" aria-live="polite">
                    <div class="alx3-results-toolbar">
                        <button type="button" class="alx3-filter-toggle" data-filter-open><?php echo $this->icon('filter'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Szűrők</button>
                        <div><b data-result-count><?php echo esc_html(number_format_i18n($data['total'])); ?></b> találat <span data-active-summary><?php echo esc_html($this->active_filter_summary($filters)); ?></span></div>
                        <label><span>Rendezés</span><select name="sort" form="alx3-sort-proxy" data-sort-select><?php echo $this->sort_options($filters['sort']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label>
                    </div>
                    <div class="alx3-result-status" data-result-status hidden></div>
                    <div class="alx3-vehicle-grid" data-vehicle-grid>
                        <?php echo $this->render_vehicle_cards($data['items']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <nav class="alx3-pagination" data-pagination aria-label="Találati oldalak">
                        <?php echo $this->render_pagination($data, $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </nav>
                </section>
            </div>
            <form id="alx3-sort-proxy" hidden></form>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<string,string|int> */
    private function render_vehicle_cards($items)
    {
        if (!$items) {
            return '<div class="alx3-empty"><b>Nincs találat</b><p>Próbálj kevesebb vagy tágabb szűrőt használni.</p></div>';
        }
        ob_start();
        foreach ($items as $vehicle) :
            ?>
            <article class="alx3-vehicle-card">
                <header>
                    <div class="alx3-brand-mark"><?php echo esc_html($this->make_initials($vehicle['make'])); ?></div>
                    <div><span><?php echo esc_html($vehicle['make']); ?></span><h2><a href="<?php echo esc_url($vehicle['url']); ?>"><?php echo esc_html(trim($vehicle['model'] . ' ' . $vehicle['generation'])); ?></a></h2></div>
                    <b class="alx3-grade is-<?php echo esc_attr(strtolower($vehicle['data_grade'])); ?>" title="Adatminőség"><?php echo esc_html($vehicle['data_grade']); ?></b>
                </header>
                <div class="alx3-card-badges">
                    <span class="is-<?php echo esc_attr(sanitize_html_class($vehicle['verification_status'])); ?>"><?php echo esc_html($vehicle['verification_label']); ?></span>
                    <?php if ($vehicle['evidence_count']) : ?><span><?php echo esc_html(number_format_i18n($vehicle['evidence_count'])); ?> bizonyíték</span><?php endif; ?>
                    <?php if ($vehicle['eu_observations']) : ?><span><?php echo esc_html(number_format_i18n($vehicle['eu_observations'])); ?> EU-megfigyelés</span><?php endif; ?>
                </div>
                <dl class="alx3-specs">
                    <div><dt>Motor</dt><dd><?php echo esc_html($vehicle['engine'] ?: 'nincs megadva'); ?></dd></div>
                    <div><dt>Motorkód</dt><dd><?php echo esc_html($vehicle['engine_code'] ?: '—'); ?></dd></div>
                    <div><dt>Üzemanyag</dt><dd><?php echo esc_html($vehicle['fuel_type'] ?: '—'); ?></dd></div>
                    <div><dt>Hengerűrtartalom</dt><dd><?php echo esc_html($vehicle['capacity_cc'] ? number_format_i18n($vehicle['capacity_cc']) . ' cm³' : '—'); ?></dd></div>
                    <div><dt>Teljesítmény</dt><dd><?php echo esc_html($vehicle['power_ps'] ? number_format_i18n($vehicle['power_ps']) . ' LE' . ($vehicle['power_kw'] ? ' / ' . number_format_i18n($vehicle['power_kw']) . ' kW' : '') : '—'); ?></dd></div>
                    <div><dt>Gyártás</dt><dd><?php echo esc_html($vehicle['years']); ?></dd></div>
                </dl>
                <footer><small>Forrásállapot: <?php echo esc_html($vehicle['verification_label']); ?></small><a href="<?php echo esc_url($vehicle['url']); ?>">Teljes adatlap <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></footer>
            </article>
            <?php
        endforeach;
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data Results. @param array<string,mixed> $filters Filters. @return string */
    private function render_pagination($data, $filters)
    {
        if ((int) $data['pages'] < 2) {
            return '';
        }
        $out = '';
        $page = (int) $data['page'];
        $pages = (int) $data['pages'];
        if ($page > 1) {
            $out .= '<a data-page="' . ($page - 1) . '" href="' . esc_url($this->filter_url($filters, $page - 1)) . '">← Előző</a>';
        }
        $out .= '<span>' . esc_html($page . ' / ' . $pages . ' oldal') . '</span>';
        if ($page < $pages) {
            $out .= '<a data-page="' . ($page + 1) . '" href="' . esc_url($this->filter_url($filters, $page + 1)) . '">Következő →</a>';
        }
        return $out;
    }

    /** @param array<string,mixed> $filters Filters. @param int $page Page. @return string */
    private function filter_url($filters, $page)
    {
        $args = array_filter($filters, static function ($value, $key) {
            return !in_array($key, array('limit', 'page'), true) && !in_array((string) $value, array('', '0'), true);
        }, ARRAY_FILTER_USE_BOTH);
        $args['page'] = $page;
        return add_query_arg($args, home_url('/autok/'));
    }

    /** @param array<int,array<string,mixed>> $options Options. @param string $selected Selected. @return string */
    private function options($options, $selected)
    {
        $html = '';
        foreach ((array) $options as $option) {
            $value = (string) ($option['value'] ?? '');
            if ('' === $value) {
                continue;
            }
            $label = $value . (!empty($option['total']) ? ' (' . number_format_i18n((int) $option['total']) . ')' : '');
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    /** @param string $selected Selected sort. @return string */
    private function sort_options($selected)
    {
        $options = array(
            'data_desc' => 'Legjobban dokumentált',
            'make_asc'  => 'Márka és modell',
            'year_desc' => 'Legújabb évjárat',
            'power_desc'=> 'Legnagyobb teljesítmény',
        );
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    /** @param array<string,mixed> $filters Filters. @return string */
    private function active_filter_summary($filters)
    {
        $active = array_filter(array(
            $filters['make'], $filters['model'], $filters['fuel'], $filters['engine_code'], $filters['grade'] ? 'minőség ' . $filters['grade'] : '',
        ));
        return $active ? '• ' . implode(' • ', $active) : '• minden autó';
    }
}
