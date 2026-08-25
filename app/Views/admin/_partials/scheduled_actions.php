<?php
/**
 * V1 schedule create + pending list (ADR-021). Not a calendar.
 *
 * @var bool $canSchedulePublish
 * @var bool $canScheduleUnpublish
 * @var list<\App\Entities\ScheduledAction> $scheduledActions
 * @var string $siteTimezone
 * @var string $scheduleCreateUrl
 * @var string $scheduleCancelBase
 * @var \App\Services\ScheduledContentService $scheduledContentService
 */
$canSchedulePublish   = ! empty($canSchedulePublish);
$canScheduleUnpublish = ! empty($canScheduleUnpublish);
$scheduledActions     = $scheduledActions ?? [];
$siteTimezone         = (string) ($siteTimezone ?? 'Asia/Jakarta');
$scheduleCreateUrl    = (string) ($scheduleCreateUrl ?? '');
$scheduleCancelBase   = (string) ($scheduleCancelBase ?? '');
$canCreate            = $canSchedulePublish || $canScheduleUnpublish;
$scheduler            = service('scheduledContentService');
?>
<?php if ($canCreate || $scheduledActions !== []) : ?>
    <section>
        <h2><?= esc('Scheduled publish / unpublish') ?></h2>
        <?php if ($canCreate && $scheduleCreateUrl !== '') : ?>
            <form method="post" action="<?= esc($scheduleCreateUrl) ?>">
                <?= csrf_field() ?>
                <div>
                    <label for="schedule_action_type"><?= esc('Action') ?></label>
                    <select id="schedule_action_type" name="action_type" required>
                        <?php if ($canSchedulePublish) : ?>
                            <option value="PUBLISH"><?= esc('Publish') ?></option>
                        <?php endif; ?>
                        <?php if ($canScheduleUnpublish) : ?>
                            <option value="UNPUBLISH"><?= esc('Unpublish') ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="schedule_execute_at"><?= esc('Execute at') ?></label>
                    <input
                        type="datetime-local"
                        id="schedule_execute_at"
                        name="execute_at"
                        required
                        step="1"
                    >
                    <p><?= esc('Site timezone: ' . $siteTimezone) ?></p>
                </div>
                <button type="submit"><?= esc('Create schedule') ?></button>
            </form>
        <?php endif; ?>

        <?php if ($scheduledActions !== []) : ?>
            <table>
                <thead>
                    <tr>
                        <th><?= esc('Action') ?></th>
                        <th><?= esc('Execute at') ?></th>
                        <th><?= esc('Timezone') ?></th>
                        <th><?= esc('Status') ?></th>
                        <th><?= esc('Result') ?></th>
                        <th><?= esc('Message') ?></th>
                        <th><?= esc('Created by') ?></th>
                        <th><?= esc('Created at') ?></th>
                        <th><?= esc('Cancel') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scheduledActions as $row) : ?>
                        <tr>
                            <td><?= esc((string) $row->action_type) ?></td>
                            <td><?= esc($scheduler->formatExecuteAtLocal((string) $row->execute_at)) ?></td>
                            <td><?= esc($siteTimezone) ?></td>
                            <td><?= esc((string) $row->status) ?></td>
                            <td><?= esc((string) ($row->result_code ?? '')) ?></td>
                            <td><?= esc((string) ($row->result_message ?? '')) ?></td>
                            <td><?= esc($row->created_by !== null ? (string) $row->created_by : '') ?></td>
                            <td><?= esc((string) $row->created_at) ?></td>
                            <td>
                                <?php
                                $canCancelThis = $row->status === 'PENDING'
                                    && (
                                        ($row->action_type === 'PUBLISH' && $canSchedulePublish)
                                        || ($row->action_type === 'UNPUBLISH' && $canScheduleUnpublish)
                                    );
                                ?>
                                <?php if ($canCancelThis && $scheduleCancelBase !== '') : ?>
                                    <form
                                        method="post"
                                        action="<?= esc($scheduleCancelBase . '/' . (int) $row->id . '/cancel') ?>"
                                    >
                                        <?= csrf_field() ?>
                                        <button type="submit"><?= esc('Cancel') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>
