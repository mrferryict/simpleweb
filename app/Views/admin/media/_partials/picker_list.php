<?php
/**
 * ACTIVE Media list fragment for the Media Picker (GET /admin/media/picker).
 *
 * @var list<\App\Entities\MediaAsset> $assets
 * @var string $mediaType
 * @var \App\Services\Media\MediaService $mediaService
 */
$attr = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<?php if ($assets === []) : ?>
    <p><?= esc('No ACTIVE ' . $mediaType . ' assets available. Upload media in the Media Library first.') ?></p>
<?php else : ?>
    <ul>
        <?php foreach ($assets as $asset) : ?>
            <?php
            $label = trim((string) ($asset->title ?? ''));
            if ($label === '') {
                $label = (string) $asset->original_filename;
            }
            $preview = $mediaService->publicImageUrl((int) $asset->id) ?? '';
            $download = '';
            if ($asset->type === 'DOCUMENT' && is_string($asset->download_token) && $asset->download_token !== '') {
                $download = '/download/document/' . $asset->download_token;
            }
            ?>
            <li>
                <button
                    type="button"
                    data-pick-media
                    data-media-id="<?= $attr((string) $asset->id) ?>"
                    data-label="<?= $attr($label) ?>"
                    data-mime="<?= $attr((string) $asset->mime_type) ?>"
                    data-size="<?= $attr((string) $asset->file_size) ?>"
                    data-preview="<?= $attr($preview) ?>"
                    data-download="<?= $attr($download) ?>"
                >
                    #<?= esc((string) $asset->id) ?> — <?= esc($label) ?>
                    (<?= esc((string) $asset->mime_type) ?>)
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
