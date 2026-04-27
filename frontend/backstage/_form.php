<?php
/**
 * Shared insight create/edit form.
 *
 * Renders a 2-column layout (matches the Projects editor):
 *   LEFT  — translation locale-cards grid (3 locales × title/excerpt/content).
 *           Save is per-locale via locale-cards.js — each card saves only
 *           to /backstage/insights/{id}/translations/{locale}.
 *   RIGHT — chrome metadata: status (publish date drives this), slug,
 *           category + label, author, featured. Action buttons at the
 *           bottom (Save chrome / Publish / Delete).
 *
 * Variables expected before include:
 * @var array  $insight        Pre-fill chrome values, or [] for empty form.
 *                              Keys: id, title, slug, category, category_label,
 *                              author, published_date, featured.
 * @var array  $translations   Per-locale map (e.g. ['fi_FI' => ['title' => …,
 *                              'excerpt' => …, 'content' => …]]). Empty on create.
 * @var array  $coverage       Per-locale {filled,total} map.
 * @var string $primary_label  Submit button label ('Create' for new, 'Save' for edit).
 * @var bool   $show_delete    If true, render the Delete button.
 */
declare(strict_types=1);

$insight       = $insight       ?? [];
$translations  = $translations  ?? [];
$coverage      = $coverage      ?? [
    'fi_FI' => ['filled' => 0, 'total' => 3],
    'en_GB' => ['filled' => 0, 'total' => 3],
    'sw_TZ' => ['filled' => 0, 'total' => 3],
];
$primary_label = $primary_label ?? 'Create';
$show_delete   = $show_delete   ?? false;

$v = static function (string $key) use ($insight): string {
    $val = $insight[$key] ?? '';
    return is_string($val) ? $val : (string) $val;
};
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$insightId = (string) ($insight['id'] ?? '');
$featured  = !empty($insight['featured']);

/**
 * Split a stored DB datetime into ['date' => 'Y-m-d', 'time' => 'H:i'] so
 * the form can render a separate date picker + time picker (chrome panel).
 */
$splitDateTime = static function (string $stored): array {
    $stored = trim($stored);
    if ($stored === '') return ['date' => '', 'time' => ''];
    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $stored)
        ?: \DateTime::createFromFormat('Y-m-d H:i',  $stored)
        ?: \DateTime::createFromFormat('Y-m-d',      $stored);
    return $dt !== false
        ? ['date' => $dt->format('Y-m-d'), 'time' => $dt->format('H:i')]
        : ['date' => '', 'time' => ''];
};
$dt   = $splitDateTime($v('published_date'));
$dStr = $dt['date'];
$tStr = $dt['time'];
$combined = ($dStr !== '' && $tStr !== '') ? ($dStr . ' ' . $tStr . ':00') : '';
?>
<div class="insight-form" id="insight-form">
    <div class="insight-form__cols">

        <!-- LEFT COLUMN — Translations (locale-cards) -->
        <div class="insight-form__col insight-form__col--left">

            <div class="insight-form__field insight-form__field--grow">
                <span class="insight-form__label">
                    Translations
                    <span class="insight-form__hint">title · excerpt · body per locale</span>
                </span>

                <div class="locale-cards-container insight-form__locales"
                     data-kind="insight"
                     data-entity-id="<?= $esc($insightId) ?>">
                    <div class="locale-cards-grid" role="tablist" aria-label="Locale translations"></div>
                    <div class="locale-cards-editor">
                        <div class="locale-cards-fields"></div>
                        <div class="locale-cards-actions">
                            <button type="button" class="btn btn--outline locale-cards-save">Save</button>
                            <span class="locale-cards-status" aria-live="polite"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN — chrome metadata + actions -->
        <div class="insight-form__col insight-form__col--right">

            <div class="insight-form__col-scroll">

                <div class="insight-form__field">
                    <label class="insight-form__label" for="if-slug">
                        Slug
                        <span class="insight-form__hint">auto-generated from fi_FI title</span>
                    </label>
                    <input type="text" id="if-slug" name="slug"
                           class="insight-form__input"
                           value="<?= $esc($v('slug')) ?>" required>
                </div>

                <div class="insight-form__field">
                    <label class="insight-form__label" for="if-category">Category</label>
                    <input type="text" id="if-category" name="category"
                           class="insight-form__input"
                           value="<?= $esc($v('category')) ?>">
                </div>

                <div class="insight-form__field">
                    <label class="insight-form__label" for="if-category-label">Category label</label>
                    <input type="text" id="if-category-label" name="category_label"
                           class="insight-form__input"
                           value="<?= $esc($v('category_label')) ?>">
                </div>

                <div class="insight-form__field">
                    <label class="insight-form__label" for="if-author">Author</label>
                    <input type="text" id="if-author" name="author"
                           class="insight-form__input"
                           value="<?= $esc($v('author')) ?>">
                </div>

                <div class="insight-form__field">
                    <span class="insight-form__label">
                        Publish date &amp; time
                        <span class="insight-form__hint">public on or after this moment</span>
                    </span>
                    <div class="insight-form__datetime-row">
                        <button type="button" id="if-publish-date-btn"
                                class="insight-form__input insight-form__time-trigger"
                                aria-label="Pick publish date">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="17" rx="2"/>
                                <line x1="3" y1="9" x2="21" y2="9"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                            </svg>
                            <span id="if-publish-date-display" data-empty-text="—"
                                  data-value="<?= $esc($dStr) ?>"><?= $dStr !== '' ? $esc($dStr) : '—' ?></span>
                        </button>
                        <button type="button" id="if-publish-time-btn"
                                class="insight-form__input insight-form__time-trigger"
                                aria-label="Pick publish time">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 7v5l3 2"/>
                            </svg>
                            <span id="if-publish-time-display" data-empty-text="--:--"><?= $tStr !== '' ? $esc($tStr) : '--:--' ?></span>
                        </button>
                    </div>
                    <input type="hidden" id="if-published-date" name="published_date"
                           value="<?= $esc($combined) ?>">
                </div>

                <div class="insight-form__field">
                    <span class="insight-form__label">Featured</span>
                    <label class="toggle-switch" for="if-featured">
                        <input type="checkbox" id="if-featured" name="featured"
                               <?= $featured ? 'checked' : '' ?>>
                        <span class="toggle-switch__track" aria-hidden="true">
                            <span class="toggle-switch__thumb"></span>
                        </span>
                        <span class="toggle-switch__label" data-on="Highlighted on the public site" data-off="Hidden from the highlighted list">
                            <?= $featured ? 'Highlighted on the public site' : 'Hidden from the highlighted list' ?>
                        </span>
                    </label>
                </div>
            </div>

            <div class="insight-form__actions">
                <?php if ($show_delete): ?>
                    <button type="button" class="btn btn--danger-outline insight-form__delete" id="if-delete">Delete</button>
                <?php else: ?>
                    <a href="/backstage/insights" class="btn btn--danger-outline">Cancel</a>
                <?php endif; ?>
                <button type="button" class="btn btn--outline" id="if-save"><?= $esc($primary_label) ?></button>
                <button type="button" class="btn btn--success-outline" id="if-publish">Publish</button>
            </div>

            <div id="if-error-mount" class="insight-form__error" style="display:none;"></div>
        </div>

    </div>
</div>

<script>
window.DAEMS_INSIGHT_FORM = {
    id:           <?= json_encode($insightId,    JSON_UNESCAPED_SLASHES) ?>,
    translations: <?= json_encode($translations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    coverage:     <?= json_encode($coverage,     JSON_UNESCAPED_SLASHES) ?>
};
</script>
