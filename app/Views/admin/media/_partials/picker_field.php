<?php
/**
 * Schema-driven IMAGE/DOCUMENT media picker field (Phase 4 / Task 4.6).
 *
 * Authoritative submitted value is integer media_id on $fieldName.
 *
 * @var string $fieldId
 * @var string $fieldName
 * @var string $mediaType IMAGE|DOCUMENT
 * @var string $valueString submitted/current media id string
 * @var callable(string): string $attr
 */
$mediaType = strtoupper(trim((string) ($mediaType ?? '')));
if ($mediaType !== 'IMAGE' && $mediaType !== 'DOCUMENT') {
    return;
}

$mediaId = 0;
if (is_string($valueString ?? null) && ctype_digit($valueString) && (int) $valueString > 0) {
    $mediaId = (int) $valueString;
}

$display   = service('mediaService')->pickerDisplay($mediaId > 0 ? $mediaId : null);
$label     = $display['label'] ?? '';
$mime      = $display['mime'] ?? '';
$size      = isset($display['size']) ? (string) $display['size'] : '';
$status    = $display['status'] ?? '';
$preview   = $display['previewUrl'] ?? '';
$download  = $display['downloadUrl'] ?? '';
$listUrl   = site_url('admin/media/picker?type=' . rawurlencode($mediaType));
$configJson = json_encode([
    'mediaId'     => $mediaId,
    'label'       => $label,
    'mime'        => $mime,
    'size'        => $size,
    'status'      => $status,
    'previewUrl'  => $preview,
    'downloadUrl' => $download,
    'type'        => $mediaType,
    'listUrl'     => $listUrl,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
?>
<div
    class="media-picker"
    data-media-type="<?= $attr($mediaType) ?>"
    data-picker-config="<?= $attr($configJson) ?>"
    x-data="mediaPicker(JSON.parse($el.dataset.pickerConfig))"
    @click="onPickClick($event)"
>
    <input
        type="hidden"
        id="<?= $attr($fieldId) ?>"
        name="<?= $attr($fieldName) ?>"
        :value="mediaId > 0 ? String(mediaId) : ''"
        value="<?= $attr($mediaId > 0 ? (string) $mediaId : '') ?>"
    >

    <div x-show="mediaId > 0" style="display:none" x-cloak>
        <p>
            <span x-text="label || ('#' + mediaId)"></span>
            <span x-show="mime"> — <span x-text="mime"></span></span>
            <span x-show="size"> (<span x-text="size"></span> bytes)</span>
            <span x-show="status && status !== 'ACTIVE'"> [<span x-text="status"></span>]</span>
        </p>
        <p x-show="previewUrl">
            <img :src="previewUrl" alt="" width="120" height="90" style="object-fit:contain;max-width:120px">
        </p>
        <p x-show="downloadUrl">
            <a :href="downloadUrl"><?= esc('Download') ?></a>
        </p>
    </div>

    <div x-show="mediaId < 1" x-cloak>
        <p><?= esc('No media selected.') ?></p>
    </div>

    <p>
        <button type="button" @click="toggleList()"><?= esc('Select Media') ?></button>
        <button type="button" @click="clear()" x-show="mediaId > 0"><?= esc('Clear') ?></button>
    </p>

    <div x-show="open" x-cloak class="media-picker-list" role="dialog" aria-label="<?= esc('Select media', 'attr') ?>">
        <p x-show="loading"><?= esc('Loading…') ?></p>
        <p x-show="!loading && loadError" x-text="loadError"></p>
        <div x-show="!loading && !loadError" x-html="listHtml"></div>
        <p><button type="button" @click="open = false"><?= esc('Close') ?></button></p>
    </div>
</div>
