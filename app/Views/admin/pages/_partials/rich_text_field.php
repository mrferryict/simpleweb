<?php
/**
 * RICH_TEXT Quill + Alpine bridge markup (Phase 3 / Task 3.5 / ADR-014).
 *
 * Initial HTML lives only in the escaped textarea (DOM source of truth).
 * Quill is UX only; RichTextSanitizer remains the security boundary.
 *
 * @var string $fieldId
 * @var string $fieldName
 * @var string $valueString
 * @var int|null $maxLength
 */

$fieldId     = is_string($fieldId ?? null) ? $fieldId : '';
$fieldName   = is_string($fieldName ?? null) ? $fieldName : '';
$valueString = is_string($valueString ?? null) ? $valueString : '';
$maxLength   = isset($maxLength) && is_int($maxLength) ? $maxLength : null;
$attr        = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<div
    class="smite-quill-editor"
    data-rich-text="quill"
    x-data="quillEditor()"
>
    <div
        x-ref="editorContainer"
        class="smite-quill-surface"
        aria-label="<?= $attr('Rich text editor') ?>"
    ></div>
    <textarea
        x-ref="backingField"
        id="<?= $attr($fieldId) ?>"
        name="<?= $attr($fieldName) ?>"
        class="smite-quill-fallback"
        rows="6"
        data-rich-text-backing="1"
        <?php if ($maxLength !== null) : ?>maxlength="<?= $attr((string) $maxLength) ?>"<?php endif; ?>
    ><?= esc($valueString) ?></textarea>
    <noscript>
        <p><?= esc('JavaScript is disabled. Use the text area above. Submitted HTML is sanitized server-side.') ?></p>
    </noscript>
</div>
