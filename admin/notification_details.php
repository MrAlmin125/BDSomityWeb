<?php
// =====================================================
// admin/notification_details.php - AJAX Handler (FULLY FIXED)
// =====================================================
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Get notification
    $notif = queryOne("SELECT * FROM notifications WHERE id = ?", 'i', [$id]);
    
    if ($notif) {
        // Mark as read when viewed
        $conn = conn();
        $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = $id");
        
        // ============================================
        // GET MEMBER NAME - FIXED
        // ============================================
        $displayName = '';
        $memberId = $notif['member_id'] ?? 0;
        
        if ($memberId > 0) {
            // First check if member_name is stored in notification
            if (!empty($notif['member_name'])) {
                $displayName = $notif['member_name'];
            } else {
                // Get from members table
                $memberInfo = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$memberId]);
                if ($memberInfo) {
                    $displayName = $memberInfo['name'];
                    // Update notification with member_name for future
                    $conn->query("UPDATE notifications SET member_name = '{$displayName}' WHERE id = $id");
                }
            }
        }
        
        // Fix message: Replace "সদস্য X" or "Member X" with actual name
        $message = $notif['message'];
        if (!empty($displayName)) {
            $message = preg_replace('/সদস্য\s+\d+/', $displayName, $message);
            $message = preg_replace('/Member\s+\d+/', $displayName, $message);
        }
        ?>
        <div class="table-responsive">
            <table class="table table-borderless table-striped">
                <tr>
                    <td style="width:130px;font-weight:600;color:#566573;">শিরোনাম</td>
                    <td><strong><?= clean($notif['title']) ?></strong></td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#566573;">বার্তা</td>
                    <td><?= nl2br(clean($message)) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#566573;">ধরন</td>
                    <td>
                        <?php 
                        $typeLabels = [
                            'general' => 'সাধারণ',
                            'announcement' => 'ঘোষণা',
                            'deposit' => 'সঞ্চয়',
                            'withdrawal' => 'উত্তোলন',
                            'loan_installment' => 'কিস্তি স্মারক',
                            'due_reminder' => 'বকেয়া স্মারক',
                            'profit_distribution' => 'লাভ বণ্টন',
                            'urgent' => 'জরুরী'
                        ];
                        echo '<span class="badge bg-primary">' . ($typeLabels[$notif['type']] ?? $notif['type']) . '</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#566573;">প্রেরণ</td>
                    <td>
                        <?php 
                        $targets = [
                            'all' => 'সব সদস্য',
                            'member' => 'নির্দিষ্ট সদস্য',
                            'admin' => 'অ্যাডমিন'
                        ];
                        echo $targets[$notif['target']] ?? $notif['target'];
                        ?>
                        <?php if ($notif['target'] === 'member' && !empty($displayName)): ?>
                        <span class="badge bg-info ms-2">
                            <i data-feather="user" style="width:12px;height:12px;"></i>
                            <?= clean($displayName) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#566573;">অগ্রাধিকার</td>
                    <td>
                        <?php 
                        $priorityColors = [
                            'low' => 'secondary',
                            'normal' => 'primary',
                            'high' => 'warning',
                            'urgent' => 'danger'
                        ];
                        $priorityLabels = [
                            'low' => 'নিম্ন',
                            'normal' => 'সাধারণ',
                            'high' => 'উচ্চ',
                            'urgent' => 'জরুরী'
                        ];
                        $color = $priorityColors[$notif['priority']] ?? 'secondary';
                        $label = $priorityLabels[$notif['priority']] ?? $notif['priority'];
                        ?>
                        <span class="badge bg-<?= $color ?>"><?= $label ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#566573;">পাঠানো হয়েছে</td>
                    <td><?= date('d M, Y h:i A', strtotime($notif['created_at'])) ?></td>
                </tr>
                <?php if ($notif['expiry_date']): ?>
                <tr>
                    <td style="font-weight:600;color:#566573;">মেয়াদ শেষ</td>
                    <td>
                        <?= date('d M, Y h:i A', strtotime($notif['expiry_date'])) ?>
                        <?php if (strtotime($notif['expiry_date']) < time()): ?>
                        <span class="badge bg-danger ms-2">মেয়াদ শেষ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="font-weight:600;color:#566573;">স্ট্যাটাস</td>
                    <td>
                        <?php if ($notif['read_at']): ?>
                        <span class="badge bg-success">
                            <i data-feather="check-circle" style="width:12px;height:12px;"></i>
                            পঠিত (<?= date('d M, Y h:i A', strtotime($notif['read_at'])) ?>)
                        </span>
                        <?php else: ?>
                        <span class="badge bg-warning">
                            <i data-feather="clock" style="width:12px;height:12px;"></i>
                            অপঠিত
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($notif['send_email'] || $notif['send_sms']): ?>
                <tr>
                    <td style="font-weight:600;color:#566573;">পাঠানো মাধ্যম</td>
                    <td>
                        <?php if ($notif['send_email']): ?>
                        <span class="badge bg-info">📧 ইমেইল</span>
                        <?php endif; ?>
                        <?php if ($notif['send_sms']): ?>
                        <span class="badge bg-success">📱 এসএমএস</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    } else {
        echo '<div class="text-center text-muted py-4">
                <i data-feather="alert-circle" style="width:32px;height:32px;display:block;margin:0 auto 10px;color:#dc3545;"></i>
                <p class="text-danger">নোটিফিকেশন পাওয়া যায়নি</p>
              </div>';
    }
} else {
    echo '<div class="text-center text-muted py-4">
            <i data-feather="alert-circle" style="width:32px;height:32px;display:block;margin:0 auto 10px;color:#dc3545;"></i>
            <p class="text-danger">Invalid ID</p>
          </div>';
}
?>