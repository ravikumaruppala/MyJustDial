<?php
/* Two-panel form builder: field widgets on the left, saved-form preview on the right.
   Expects: $form, $fields (from loadBuilderFields), $fieldData, $isNewForm. */
$base = e($config['base_url']);
?>
<div class="fb-page">
    <div class="fb-actions-row">
        <a class="fb-btn fb-btn-back" href="<?= $base ?>/admin/toolbox/form-manager/index.php"><i class="fa fa-arrow-left"></i> Back</a>
        <div class="fb-actions-right">
            <button type="button" class="fb-btn fb-btn-outline" id="copyShortcode"><i class="fa fa-files-o"></i> Copy Shortcode</button>
            <button type="button" class="fb-btn fb-btn-dark" id="toggleFormSettings" aria-expanded="false"><i class="fa fa-cogs"></i> Edit Form Settings</button>
        </div>
    </div>

    <form id="formBuilder" method="post" action="<?= $base ?>/admin/toolbox/form-manager/save.php" onsubmit="return false;">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form_id" value="<?= (int)$form['form_id'] ?>">

        <section class="fb-settings is-collapsed" id="formSettingsCard">
            <div class="fb-settings-grid">
                <div>
                    <label for="formNickname">Form Nickname <span class="fb-star">*</span></label>
                    <input id="formNickname" name="form_nickname" value="<?= e($form['form_nickname']) ?>" placeholder="Example: Member Sign Up Free">
                    <small>Used internally to identify this form.</small>
                </div>
                <div>
                    <label for="formVariable">Form Variable Name</label>
                    <input id="formVariable" value="<?= e($form['form_name']) ?>" readonly>
                    <small>Generated automatically from the nickname. Shortcode: <code id="shortcodePreview"><?= e($form['short_code'] ?: '[form=' . $form['form_name'] . ']') ?></code></small>
                </div>
                <div>
                    <label for="formTitle">Form Header Title</label>
                    <input id="formTitle" name="form_title" value="<?= e($form['form_title']) ?>" placeholder="Form header title">
                </div>
                <div>
                    <label for="formDescription">Form Description</label>
                    <input id="formDescription" name="form_desc" value="<?= e($form['form_desc']) ?>" placeholder="Short description (optional)">
                </div>
            </div>
        </section>

        <div class="fb-layout">
            <!-- LEFT: one widget per field, drag to reorder -->
            <section class="fb-left" aria-label="Form fields">
                <div class="fb-left-bar">
                    <span class="fb-left-title" id="fbFormName"><?= e($form['form_nickname']) ?></span>
                    <div class="fb-search"><i class="fa fa-search"></i><input id="fbSearch" type="search" placeholder="Search by Keyword" aria-label="Search by keyword"></div>
                    <button type="button" class="fb-save" id="fbSave">Save Changes</button>
                </div>
                <div class="fb-list" id="fbList"></div>
            </section>

            <!-- RIGHT: preview of the form as saved in the database -->
            <section class="fb-right" aria-label="Form preview">
                <div class="fb-preview-head">Form Preview</div>
                <div class="fb-preview-body" id="fbPreview"></div>
            </section>
        </div>
    </form>
    <div class="fb-toast" id="fbToast" role="status" aria-live="polite"></div>
</div>
<script>
window.MJD_FIELD_GROUPS = <?= json_encode(formFieldGroups(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.MJD_FIELD_DATA = <?= json_encode($fieldData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.MJD_INITIAL_FIELDS = <?= json_encode($fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.MJD_IS_NEW_FORM = <?= $isNewForm ? 'true' : 'false' ?>;
window.MJD_SAVE_URL = <?= json_encode($config['base_url'] . '/admin/toolbox/form-manager/save.php') ?>;
window.MJD_PHP_PREVIEW_URL = <?= json_encode($config['base_url'] . '/admin/toolbox/form-manager/php-preview.php') ?>;
</script>
<script src="<?= $base ?>/assets/js/form-builder.js?v=<?= (int)filemtime(__DIR__ . '/../../../assets/js/form-builder.js') ?>"></script>
