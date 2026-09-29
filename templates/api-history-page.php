<?php
global $wpdb;
$table_name = $wpdb->prefix . 'api_response_history';
$results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
?>
<style>
    .rmfl-history-wrap { max-width: 1200px; margin-top: 20px; }
    .rmfl-history-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
    .rmfl-history-header h1 { margin: 0; font-size: 22px; }
    .rmfl-history-count { color: #646970; font-size: 13px; }

    .rmfl-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
    }
    .rmfl-status-success { background: #edf9ee; color: #1a7a2e; }
    .rmfl-status-error { background: #fbeaea; color: #a7161d; }
    .rmfl-status-badge .dashicons { font-size: 14px; width: 14px; height: 14px; }

    .rmfl-response-cell {
        max-width: 260px;
        max-height: 70px;
        overflow: auto;
        display: block;
        font-family: Consolas, Monaco, monospace;
        font-size: 11.5px;
        color: #50575e;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .rmfl-empty-state {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        padding: 48px 24px;
        text-align: center;
        color: #646970;
    }
    .rmfl-empty-state .dashicons { font-size: 36px; width: 36px; height: 36px; color: #c3c4c7; margin-bottom: 8px; }
</style>

<div class="wrap rmfl-history-wrap">
    <div class="rmfl-history-header">
        <h1>API Response History</h1>
        <span class="rmfl-history-count"><?php echo count($results); ?> lead<?php echo count($results) === 1 ? '' : 's'; ?> logged</span>
    </div>

    <?php if (empty($results)) : ?>
        <div class="rmfl-empty-state">
            <span class="dashicons dashicons-clipboard"></span>
            <p>No leads have been sent yet. Once a form submits, its delivery attempt will show up here.</p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>API</th>
                    <th style="width:110px;">Status</th>
                    <th>Customer name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Extra Fields</th>
                    <th>Response</th>
                    <th style="width:150px;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row) : $is_success = $row['status'] === 'success'; ?>
                    <tr>
                        <td><?php echo esc_html($row['id']); ?></td>
                        <td><?php echo esc_html($row['api_name']); ?></td>
                        <td>
                            <span class="rmfl-status-badge <?php echo $is_success ? 'rmfl-status-success' : 'rmfl-status-error'; ?>">
                                <span class="dashicons <?php echo $is_success ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php echo esc_html(ucfirst($row['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($row['customer_name']); ?></td>
                        <td><?php echo esc_html($row['customer_phone']); ?></td>
                        <td><?php echo esc_html($row['customer_email']); ?></td>
                        <td><?php echo esc_html($row['message']); ?></td>
                        <td>
                            <?php
                            $extra_fields = json_decode($row['extra_fields'] ?? '', true);
                            if (is_array($extra_fields) && !empty($extra_fields)) {
                                $pairs = [];
                                foreach ($extra_fields as $field_key => $field_value) {
                                    $pairs[] = esc_html($field_key) . ': ' . esc_html($field_value);
                                }
                                echo implode('<br>', $pairs);
                            }
                            ?>
                        </td>
                        <td><span class="rmfl-response-cell"><?php echo esc_html($row['response_body']); ?></span></td>
                        <td><?php echo esc_html($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
