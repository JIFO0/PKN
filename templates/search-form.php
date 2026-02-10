<?php
/**
 * Template for the science communities search form
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get all available tags
global $wpdb;
$tags_table = $wpdb->prefix . 'science_tags';
$faculties_table = $wpdb->prefix . 'science_faculties';
$all_tags = $wpdb->get_results("SELECT id, tag_name FROM $tags_table ORDER BY tag_name ASC");
$all_faculties = $wpdb->get_results("SELECT id, faculty_name FROM $faculties_table ORDER BY faculty_name ASC");

// Get any existing search query
$search_query = isset($_GET['sc_search']) ? sanitize_text_field($_GET['sc_search']) : '';
$selected_tags = isset($_GET['sc_tags']) ? array_map('intval', (array)$_GET['sc_tags']) : array();
$selected_faculties = isset($_GET['sc_faculties']) ? array_map('intval', (array)$_GET['sc_faculties']) : array();
?>

<div class="sc-search-wrapper">
    <div class="container">
        <!-- Header Section -->
        <div class="sc-search-header">
            <h1 class="underline">Wyszukaj koło naukowe</h1>
            <p class="sc-search-description">
                Przeglądaj bazę kół naukowych Uniwersytetu Gdańskiego. Użyj filtrów, aby znaleźć koło odpowiadające Twoim zainteresowaniom.
            </p>
        </div>

        <!-- Search Form -->
        <div class="sc-search-container">
            <?php 
                $results_page = site_url('/results/');
                ?>
                <form class="sc-search-form" action="<?php echo esc_url($results_page); ?>" method="get" id="sc-search-form">
                <!-- Main Search Box -->
                <div class="sc-search-main">
                    <div class="sc-search-box">
                        <svg class="sc-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input 
                            type="text" 
                            name="sc_search" 
                            id="sc-search-input" 
                            value="<?php echo esc_attr($search_query); ?>" 
                            placeholder="Wpisz nazwę koła naukowego..."
                            aria-label="<?php esc_attr_e('Wyszukaj', 'science-communities'); ?>"
                        >
                        <button type="submit" class="sc-search-button">
                            <span>Szukaj</span>
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="sc-filters-toggle-wrapper">
                    <button type="button" class="sc-filters-toggle" id="sc-filters-toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <span>Filtry zaawansowane</span>
                        <svg class="sc-toggle-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>

                <div class="sc-advanced-filters" id="sc-advanced-filters" style="display: none;">
                    <?php if (!empty($all_faculties)): ?>
                    <!-- Faculty Filter -->
                    <div class="sc-filter-section">
                        <label class="sc-filter-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span>Wydział</span>
                        </label>
                        <div class="sc-filter-options">
                            <?php foreach ($all_faculties as $faculty): ?>
                            <label class="sc-filter-option <?php echo in_array($faculty->id, $selected_faculties) ? 'selected' : ''; ?>">
                                <input 
                                    type="checkbox" 
                                    name="sc_faculties[]" 
                                    value="<?php echo esc_attr($faculty->id); ?>"
                                    <?php checked(in_array($faculty->id, $selected_faculties)); ?>
                                    class="sc-faculty-checkbox"
                                >
                                <span class="sc-option-text"><?php echo esc_html($faculty->faculty_name); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($all_tags)): ?>
                    <!-- Tags Filter -->
                    <div class="sc-filter-section">
                        <label class="sc-filter-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                            <span>Tematyka</span>
                        </label>
                        <div class="sc-filter-options sc-tags-grid">
                            <?php foreach ($all_tags as $tag): ?>
                            <label class="sc-filter-option sc-tag-option <?php echo in_array($tag->id, $selected_tags) ? 'selected' : ''; ?>">
                                <input 
                                    type="checkbox" 
                                    name="sc_tags[]" 
                                    value="<?php echo esc_attr($tag->id); ?>"
                                    <?php checked(in_array($tag->id, $selected_tags)); ?>
                                    class="sc-tag-checkbox"
                                >
                                <span class="sc-option-text"><?php echo esc_html($tag->tag_name); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Filter Actions -->
                    <div class="sc-filter-actions">
                        <?php if (!empty($selected_tags) || !empty($selected_faculties) || !empty($search_query)): ?>
                        <button type="button" class="sc-clear-filters" id="sc-clear-filters-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Wyczyść filtry
                        </button>
                        <?php endif; ?>
                        <button type="submit" class="sc-apply-filters">
                            Zastosuj filtry
                        </button>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <?php if (!empty($selected_tags) || !empty($selected_faculties) || !empty($search_query)): ?>
                <div class="sc-active-filters">
                    <div class="sc-active-filters-label">Aktywne filtry:</div>
                    <div class="sc-active-filters-list">
                        <?php if (!empty($search_query)): ?>
                        <span class="sc-active-filter-badge">
                            <span class="sc-filter-icon">🔍</span>
                            <?php echo esc_html($search_query); ?>
                        </span>
                        <?php endif; ?>
                        
                        <?php foreach ($selected_faculties as $faculty_id): 
                            $faculty = $wpdb->get_row($wpdb->prepare("SELECT faculty_name FROM $faculties_table WHERE id = %d", $faculty_id));
                            if ($faculty):
                        ?>
                        <span class="sc-active-filter-badge sc-faculty-badge">
                            <span class="sc-filter-icon">🏛️</span>
                            <?php echo esc_html($faculty->faculty_name); ?>
                        </span>
                        <?php endif; endforeach; ?>
                        
                        <?php foreach ($selected_tags as $tag_id): 
                            $tag = $wpdb->get_row($wpdb->prepare("SELECT tag_name FROM $tags_table WHERE id = %d", $tag_id));
                            if ($tag):
                        ?>
                        <span class="sc-active-filter-badge sc-tag-badge">
                            <span class="sc-filter-icon">🏷️</span>
                            <?php echo esc_html($tag->tag_name); ?>
                        </span>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="sc-quick-links">
            <h3>Popularne kategorie:</h3>
            <div class="sc-quick-links-grid">
                <?php 
                $popular_tags = array_slice($all_tags, 0, 6);
                foreach ($popular_tags as $tag): 
                ?>
                <a href="?sc_tags[]=<?php echo esc_attr($tag->id); ?>" class="sc-quick-link">
                    <?php echo esc_html($tag->tag_name); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle advanced filters
    const toggleBtn = document.getElementById('sc-filters-toggle');
    const advancedFilters = document.getElementById('sc-advanced-filters');
    
    if (toggleBtn && advancedFilters) {
        toggleBtn.addEventListener('click', function() {
            if (advancedFilters.style.display === 'none') {
                advancedFilters.style.display = 'block';
                toggleBtn.classList.add('active');
            } else {
                advancedFilters.style.display = 'none';
                toggleBtn.classList.remove('active');
            }
        });
    }
    
    // Handle filter checkbox styling
    const checkboxes = document.querySelectorAll('.sc-tag-checkbox, .sc-faculty-checkbox');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const label = this.closest('.sc-filter-option');
            if (this.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    });
    
    // Clear filters button
    const clearBtn = document.getElementById('sc-clear-filters-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.querySelectorAll('.sc-tag-checkbox, .sc-faculty-checkbox').forEach(function(cb) {
                cb.checked = false;
                cb.closest('.sc-filter-option').classList.remove('selected');
            });
            document.getElementById('sc-search-input').value = '';
            document.getElementById('sc-search-form').submit();
        });
    }
});
</script>