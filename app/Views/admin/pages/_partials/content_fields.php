<?php
/**
 * Server-rendered Content Schema fields for Page forms (Phase 3 / Task 3.3 + 3.5).
 *
 * Schema is resolved by PageService / ThemeService — never loaded in this View.
 * RICH_TEXT uses Quill + Alpine `quillEditor` with textarea fallback (ADR-014).
 * IMAGE / DOCUMENT use Media Picker (Task 4.6); backing value remains media_id.
 *
 * @var array<string, array<string, mixed>> $contentSchema
 * @var array<string, mixed> $contentPayload
 * @var array<string, string> $errors
 */

$contentSchema  = is_array($contentSchema ?? null) ? $contentSchema : [];
$contentPayload = is_array($contentPayload ?? null) ? $contentPayload : [];
$errors         = is_array($errors ?? null) ? $errors : [];

/**
 * Escape for HTML attribute context without encoding structural [ ] used in PHP field names.
 * Schema keys are developer-trusted; user values still pass through this helper.
 */
$attr = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<?php if ($contentSchema === []) : ?>
    <p><?= esc('No content fields are defined for this template.') ?></p>
<?php else : ?>
    <?php foreach ($contentSchema as $fieldKey => $definition) : ?>
        <?php
        if (! is_string($fieldKey) || ! is_array($definition)) {
            continue;
        }

        $type      = isset($definition['type']) && is_string($definition['type']) ? $definition['type'] : '';
        $label     = isset($definition['label']) && is_string($definition['label']) && $definition['label'] !== ''
            ? $definition['label']
            : $fieldKey;
        $required  = ! empty($definition['required']);
        $maxLength = null;
        if (isset($definition['validation']) && is_array($definition['validation'])
            && isset($definition['validation']['max_length'])
            && is_int($definition['validation']['max_length'])
        ) {
            $maxLength = $definition['validation']['max_length'];
        }
        $fieldId    = 'content_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $fieldKey);
        $fieldName  = 'content[' . $fieldKey . ']';
        $value      = $contentPayload[$fieldKey] ?? null;
        $fieldError = isset($errors[$fieldKey]) ? (string) $errors[$fieldKey] : null;
        ?>

        <?php if ($type === 'REPEATABLE') : ?>
            <?php
            $minItems = isset($definition['minimum_items']) && is_int($definition['minimum_items'])
                ? $definition['minimum_items']
                : 0;
            $maxItems = isset($definition['maximum_items']) && is_int($definition['maximum_items'])
                ? $definition['maximum_items']
                : 1;
            if ($maxItems < 1) {
                $maxItems = 1;
            }
            $childFields = isset($definition['fields']) && is_array($definition['fields'])
                ? $definition['fields']
                : [];
            $rows = is_array($value) ? $value : [];
            ?>
            <fieldset data-content-field="<?= $attr($fieldKey) ?>" data-content-type="REPEATABLE">
                <legend>
                    <?= esc($label) ?>
                    <?php if ($required) : ?>
                        <span><?= esc('(required)') ?></span>
                    <?php endif; ?>
                </legend>
                <p><?= esc(sprintf('Items: %d–%d (empty rows are ignored).', $minItems, $maxItems)) ?></p>
                <?php if ($fieldError !== null) : ?>
                    <p><?= esc($fieldError) ?></p>
                <?php endif; ?>

                <?php for ($i = 0; $i < $maxItems; $i++) : ?>
                    <?php
                    $row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : [];
                    ?>
                    <fieldset data-repeatable-index="<?= $attr((string) $i) ?>">
                        <legend><?= esc(sprintf('Item %d', $i + 1)) ?></legend>
                        <?php foreach ($childFields as $childKey => $childDef) : ?>
                            <?php
                            if (! is_string($childKey) || ! is_array($childDef)) {
                                continue;
                            }
                            $childType = isset($childDef['type']) && is_string($childDef['type'])
                                ? $childDef['type']
                                : 'TEXT';
                            $childLabel = isset($childDef['label']) && is_string($childDef['label']) && $childDef['label'] !== ''
                                ? $childDef['label']
                                : $childKey;
                            $childRequired = ! empty($childDef['required']);
                            $childId       = $fieldId . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $childKey);
                            $childName     = 'content[' . $fieldKey . '][' . $i . '][' . $childKey . ']';
                            $childValue    = $row[$childKey] ?? '';
                            $childErrorKey = $fieldKey . '.' . $i . '.' . $childKey;
                            $childError    = isset($errors[$childErrorKey]) ? (string) $errors[$childErrorKey] : null;
                            $childMax      = null;
                            if (isset($childDef['validation']) && is_array($childDef['validation'])
                                && isset($childDef['validation']['max_length'])
                                && is_int($childDef['validation']['max_length'])
                            ) {
                                $childMax = $childDef['validation']['max_length'];
                            }
                            $inputType = match ($childType) {
                                'URL', 'YOUTUBE_URL' => 'url',
                                default => 'text',
                            };
                            $childValueString = is_scalar($childValue) ? (string) $childValue : '';
                            ?>
                            <div>
                                <label for="<?= $attr($childId) ?>">
                                    <?= esc($childLabel) ?>
                                    <?php if ($childRequired) : ?>
                                        <span><?= esc('(required when row used)') ?></span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($childType === 'RICH_TEXT') : ?>
                                    <?= view('admin/pages/_partials/rich_text_field', [
                                        'fieldId'     => $childId,
                                        'fieldName'   => $childName,
                                        'valueString' => $childValueString,
                                        'maxLength'   => $childMax,
                                    ]) ?>
                                <?php elseif ($childType === 'IMAGE' || $childType === 'DOCUMENT') : ?>
                                    <?= view('admin/media/_partials/picker_field', [
                                        'fieldId'     => $childId,
                                        'fieldName'   => $childName,
                                        'mediaType'   => $childType,
                                        'valueString' => $childValueString,
                                        'attr'        => $attr,
                                    ]) ?>
                                <?php elseif ($childType === 'TEXTAREA') : ?>
                                    <textarea
                                        id="<?= $attr($childId) ?>"
                                        name="<?= $attr($childName) ?>"
                                        rows="3"
                                        <?php if ($childMax !== null) : ?>maxlength="<?= $attr((string) $childMax) ?>"<?php endif; ?>
                                    ><?= esc($childValueString) ?></textarea>
                                <?php else : ?>
                                    <input
                                        type="<?= $attr($inputType) ?>"
                                        id="<?= $attr($childId) ?>"
                                        name="<?= $attr($childName) ?>"
                                        value="<?= $attr($childValueString) ?>"
                                        <?php if ($childMax !== null && $inputType === 'text') : ?>
                                            maxlength="<?= $attr((string) $childMax) ?>"
                                        <?php endif; ?>
                                    >
                                <?php endif; ?>
                                <?php if ($childError !== null) : ?>
                                    <p><?= esc($childError) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endfor; ?>
            </fieldset>
        <?php else : ?>
            <div data-content-field="<?= $attr($fieldKey) ?>" data-content-type="<?= $attr($type) ?>">
                <label for="<?= $attr($fieldId) ?>">
                    <?= esc($label) ?>
                    <?php if ($required) : ?>
                        <span><?= esc('(required)') ?></span>
                    <?php endif; ?>
                </label>

                <?php
                $valueString = is_scalar($value) ? (string) $value : '';
                if (($type === 'IMAGE' || $type === 'DOCUMENT')
                    && ! (is_int($value) || (is_string($value) && ctype_digit($value)))
                ) {
                    $valueString = '';
                }
                ?>

                <?php if ($type === 'TEXTAREA') : ?>
                    <textarea
                        id="<?= $attr($fieldId) ?>"
                        name="<?= $attr($fieldName) ?>"
                        rows="4"
                        <?php if ($maxLength !== null) : ?>maxlength="<?= $attr((string) $maxLength) ?>"<?php endif; ?>
                    ><?= esc($valueString) ?></textarea>
                <?php elseif ($type === 'RICH_TEXT') : ?>
                    <?= view('admin/pages/_partials/rich_text_field', [
                        'fieldId'     => $fieldId,
                        'fieldName'   => $fieldName,
                        'valueString' => $valueString,
                        'maxLength'   => $maxLength,
                    ]) ?>
                <?php elseif ($type === 'IMAGE' || $type === 'DOCUMENT') : ?>
                    <?= view('admin/media/_partials/picker_field', [
                        'fieldId'     => $fieldId,
                        'fieldName'   => $fieldName,
                        'mediaType'   => $type,
                        'valueString' => $valueString,
                        'attr'        => $attr,
                    ]) ?>
                <?php elseif ($type === 'URL' || $type === 'YOUTUBE_URL') : ?>
                    <input
                        type="url"
                        id="<?= $attr($fieldId) ?>"
                        name="<?= $attr($fieldName) ?>"
                        value="<?= $attr($valueString) ?>"
                        placeholder="<?= $attr($type === 'YOUTUBE_URL' ? 'https://www.youtube.com/watch?v=…' : 'https://') ?>"
                    >
                <?php else : ?>
                    <?php /* TEXT and any unknown scalar → safe text input */ ?>
                    <input
                        type="text"
                        id="<?= $attr($fieldId) ?>"
                        name="<?= $attr($fieldName) ?>"
                        value="<?= $attr($valueString) ?>"
                        <?php if ($maxLength !== null) : ?>maxlength="<?= $attr((string) $maxLength) ?>"<?php endif; ?>
                    >
                <?php endif; ?>

                <?php if ($fieldError !== null) : ?>
                    <p><?= esc($fieldError) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
