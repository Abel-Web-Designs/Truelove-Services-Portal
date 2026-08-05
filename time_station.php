<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Restrict to time_station role only
// if (getUserRole() !== 'time_station' && getUserRole() !== 'admin') {
//     header("Location: dashboard.php");
//     exit();
// }

date_default_timezone_set('America/Indiana/Indianapolis');

$message = '';
$alertClass = '';
$playSound = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    $pin = trim($_POST['pin']);

    $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE is_active = 1 AND pin = ?");
    $stmt->execute([$pin]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $emp_id = (int)$user['id'];
        $name   = $user['name'];

        $stmt = $pdo->prepare("
            SELECT clock_type
            FROM time_logs
            WHERE employee_id = ?
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        $stmt->execute([$emp_id]);
        $lastPunch = $stmt->fetchColumn();

        $newPunch = ($lastPunch === 'in') ? 'out' : 'in';
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO time_logs (employee_id, clock_type, timestamp)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$emp_id, $newPunch, $now]);

        $message = $name . " successfully clocked " . strtoupper($newPunch) . " at " . date("g:i A");
        $alertClass = 'success';
        $playSound = 'success';
    } else {
        $message = "Invalid PIN. Please try again.";
        $alertClass = 'danger';
        $playSound = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Time Station</title>

    <meta name="viewport"
          content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Time Station">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        html {
            -webkit-text-size-adjust: 100%;
            height: 100%;
        }

        body {
            background-color: #121212;
            color: #f8f9fa;
            min-height: 100%;
            height: 100%;
            overflow: hidden;
            overscroll-behavior: none;
            touch-action: manipulation;
        }

        .kiosk-wrap {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .station-card {
            width: 100%;
            max-width: 460px;
            margin: 0;
        }

        #pin {
            font-size: 2.2rem;
            letter-spacing: 10px;
            background-color: #000;
            color: #0f0;
        }

        input,
        select,
        textarea,
        button {
            font-size: 16px;
        }

        .keypad,
        .keypad * {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
        }

        .keypad .keypad-btn,
        .keypad button[type="submit"] {
            min-height: 74px;
            font-size: 2rem;
            border-width: 2px;
            border-radius: 14px;
            padding: 18px;
        }

        .keypad .keypad-btn:active,
        .keypad button[type="submit"]:active {
            transform: scale(0.97);
            filter: brightness(1.15);
        }

        .alert {
            font-size: 1.35rem;
            font-weight: 600;
            padding: 1.25rem;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="kiosk-wrap">
    <div class="card station-card shadow-lg bg-dark text-light">
        <div class="card-header bg-success text-center">
            <h4 class="mb-0 text-light">Time Station</h4>
        </div>

        <div class="card-body">

            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($alertClass) ?> text-center">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="pinForm" autocomplete="off">
                <input
                    type="password"
                    id="pin"
                    name="pin"
                    class="form-control text-center mb-3"
                    maxlength="4"
                    readonly
                    required
                >

                <div class="keypad">
                    <div class="row g-2">
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <div class="col-4">
                                <button
                                    type="button"
                                    class="btn btn-outline-light w-100 keypad-btn"
                                    data-key="<?= $i ?>">
                                    <?= $i ?>
                                </button>
                            </div>
                        <?php endfor; ?>

                        <div class="col-4">
                            <button
                                type="button"
                                class="btn btn-danger w-100 keypad-btn text-light"
                                data-key="clear">C</button>
                        </div>

                        <div class="col-4">
                            <button
                                type="button"
                                class="btn btn-outline-light w-100 keypad-btn"
                                data-key="0">0</button>
                        </div>

                        <div class="col-4">
                            <button type="submit" class="btn btn-success w-100 text-light">✔</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<audio id="successSound" src="assets/sounds/success.mp3" preload="auto"></audio>
<audio id="errorSound" src="assets/sounds/error.mp3" preload="auto"></audio>

<script>
    const pinInput = document.getElementById('pin');
    const pinForm = document.getElementById('pinForm');
    const keypad = document.querySelector('.keypad');

    const successSound = document.getElementById('successSound');
    const errorSound = document.getElementById('errorSound');

    successSound.volume = 0.4;
    errorSound.volume = 0.5;

    let submitting = false;
    let lastTapTime = 0;
    let lastInteraction = Date.now();
    let audioUnlocked = false;

    // Keep the session alive every 3 minutes
    setInterval(() => {
        fetch('ping.php', {
            credentials: 'include',
            cache: 'no-store'
        }).catch(() => {});
    }, 3 * 60 * 1000);

    // Hard refresh every 15 minutes no matter what
    setInterval(() => {
        window.location.reload();
    }, 15 * 60 * 1000);

    // Only refresh if idle for 5 minutes
    setInterval(() => {
        const idleMs = Date.now() - lastInteraction;
        if (!submitting && idleMs >= 5 * 60 * 1000) {
            window.location.reload();
        }
    }, 60 * 1000);

    function markInteraction() {
        lastInteraction = Date.now();
    }

    ['touchstart', 'touchend', 'pointerdown', 'mousedown', 'keydown', 'click'].forEach(evt => {
        document.addEventListener(evt, markInteraction, { passive: true });
    });

    pinInput.addEventListener('focus', () => pinInput.blur());
    pinInput.addEventListener('click', () => pinInput.blur());

    document.addEventListener('touchmove', function (e) {
        e.preventDefault();
    }, { passive: false });

    function unlockAudio() {
        if (audioUnlocked) return;
        audioUnlocked = true;

        const sounds = [successSound, errorSound];

        sounds.forEach(sound => {
            try {
                sound.muted = true;
                sound.currentTime = 0;

                const playPromise = sound.play();
                if (playPromise && typeof playPromise.then === 'function') {
                    playPromise.then(() => {
                        sound.pause();
                        sound.currentTime = 0;
                        sound.muted = false;
                    }).catch(() => {
                        sound.muted = false;
                    });
                } else {
                    sound.pause();
                    sound.currentTime = 0;
                    sound.muted = false;
                }
            } catch (e) {
                sound.muted = false;
            }
        });
    }

    document.addEventListener('touchstart', unlockAudio, { once: true, passive: true });
    document.addEventListener('pointerdown', unlockAudio, { once: true, passive: true });
    document.addEventListener('mousedown', unlockAudio, { once: true, passive: true });

    function playFeedback(soundEl) {
        if (!soundEl) return;

        try {
            soundEl.pause();
            soundEl.currentTime = 0;

            const p = soundEl.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {});
            }
        } catch (e) {
            // Ignore playback errors on kiosk devices
        }
    }

    function handleKeyPress(key) {
        if (submitting) return;

        markInteraction();

        if (navigator.vibrate) {
            navigator.vibrate(15);
        }

        if (key === 'clear') {
            pinInput.value = '';
            return;
        }

        if (pinInput.value.length < 4) {
            pinInput.value += key;
        }
    }

    const usePointer = window.PointerEvent !== undefined;

    function onKeypadEvent(e) {
        const btn = e.target.closest('.keypad-btn');
        if (!btn) return;

        e.preventDefault();
        markInteraction();

        const now = Date.now();
        if (now - lastTapTime < 40) return;
        lastTapTime = now;

        handleKeyPress(btn.dataset.key);
    }

    if (usePointer) {
        keypad.addEventListener('pointerdown', onKeypadEvent, { passive: false });
    } else {
        keypad.addEventListener('touchstart', onKeypadEvent, { passive: false });
        keypad.addEventListener('mousedown', onKeypadEvent);
    }

    pinForm.addEventListener('submit', function (e) {
        markInteraction();

        if (submitting) {
            e.preventDefault();
            return;
        }

        if (pinInput.value.length !== 4) {
            e.preventDefault();
            return;
        }

        submitting = true;
    });

    <?php if ($playSound === 'success'): ?>
    window.addEventListener('load', function () {
        playFeedback(successSound);
        setTimeout(() => {
            pinInput.value = '';
        }, 1200);
    });
    <?php elseif ($playSound === 'error'): ?>
    window.addEventListener('load', function () {
        playFeedback(errorSound);
        setTimeout(() => {
            pinInput.value = '';
        }, 1200);
    });
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>