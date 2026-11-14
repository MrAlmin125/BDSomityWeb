<?php
// =====================================================
// includes/footer.php - Complete Footer with Dynamic Copyright
// =====================================================
?>

</main><!-- /main-content -->

<footer class="py-3 text-center" style="margin-left:var(--sidebar-w);border-top:1px solid var(--border);background:white;font-size:12px;color:var(--text-muted);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <!-- Left: Copyright -->
            <div class="col-md-6 text-center text-md-start">
                <?php 
                // Get somity name from settings
                $somity_name = getSetting('somity_name', 'বাংলাদেশ সমিতি');
                ?>
              <?=date ('Y') ?> &copy; <?= clean($somity_name) ?>
            </div>
            
            <!-- Right: Developer Credit -->
            <div class="col-md-6 text-center text-md-end">
                <i class="fa-brands fa-edge-legacy" style="color: rgb(27, 79, 114); width:12px; height:12px; vertical-align:middle; margin-right:4px;"></i>
                   <span style="font-size:11px;"> Developed by </span>
                     <a href="https://amin.free.nf/" target="_blank" rel="noopener noreferrer" style="color: #2E86C1; text-decoration: none; font-weight: 600; font-size:12px; transition: all 0.3s ease;" onmouseover="this.style.color='#1B4F72'; this.style.textDecoration='underline';" onmouseout="this.style.color='#2E86C1'; this.style.textDecoration='none';"> Mr.Amin </a>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================
     SCRIPTS
     ============================================ -->

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================
// INITIALIZE FEATHER ICONS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

// ============================================
// SIDEBAR TOGGLE (MOBILE)
// ============================================
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggle  = document.getElementById('sidebarToggle');

if (toggle) {
    toggle.addEventListener('click', function() {
        if (sidebar) sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    });
}

if (overlay) {
    overlay.addEventListener('click', function() {
        if (sidebar) sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    });
}

// ============================================
// FIX FOOTER MARGIN ON MOBILE
// ============================================
function fixFooterMargin() {
    const footer = document.querySelector('footer');
    if (!footer) return;
    
    if (window.innerWidth <= 768) {
        footer.style.marginLeft = '0';
    } else {
        footer.style.marginLeft = 'var(--sidebar-w)';
    }
}

// Run on load and resize
document.addEventListener('DOMContentLoaded', fixFooterMargin);
window.addEventListener('resize', fixFooterMargin);

// ============================================
// AUTO-DISMISS ALERTS AFTER 5 SECONDS
// ============================================
document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }
    }, 5000);
});

// ============================================
// CONFIRM DELETE DIALOGS
// ============================================
document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        const message = this.dataset.confirm || 'আপনি কি নিশ্চিত?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});

// ============================================
// FORMAT CURRENCY INPUTS
// ============================================
document.querySelectorAll('input[data-type="currency"]').forEach(function(el) {
    el.addEventListener('blur', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
});

// ============================================
// TOGGLE PASSWORD VISIBILITY (GLOBAL)
// ============================================
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const icon = document.getElementById(fieldId + 'Eye');
    if (field.type === 'password') {
        field.type = 'text';
        if (icon) icon.setAttribute('data-feather', 'eye-off');
    } else {
        field.type = 'password';
        if (icon) icon.setAttribute('data-feather', 'eye');
    }
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// ============================================
// EXTRA JS FROM PAGE (if any)
// ============================================
<?= $extraJS ?? '' ?>
</script>

</body>
</html>