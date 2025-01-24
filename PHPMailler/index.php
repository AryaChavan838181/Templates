<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

session_start();

function sendOtp($email) {
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 900; // 15 minutes

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email';                   
        $mail->Password   = 'your-app password'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Or SMTPS if port 465
        $mail->Port       = 587; // Or 465

        $mail->setFrom('your-email', 'Your Name');
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP';
        $mail->Body    = "Your OTP is: " . $otp;
        $mail->AltBody = "Your OTP is: " . $otp;

        $mail->send();
        return ['status' => true, 'message' => 'OTP sent successfully'];
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage()); // Log the error
        return ['status' => false, 'message' => 'Error sending OTP. Please try again later.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_otp':
                $email = $_POST['email'];
                if (filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, "@vit.edu")) {
                    echo json_encode(sendOtp($email));
                } else {
                    echo json_encode(['status' => false, 'message' => 'Invalid VIT email format']);
                }
                break;
            case 'verify_otp':
                $otp = $_POST['otp'];
                if (isset($_SESSION['otp'], $_SESSION['otp_expiry']) && time() <= $_SESSION['otp_expiry']) {
                    if ($otp == $_SESSION['otp']) {
                        $_SESSION['verified_email'] = true;
                        unset($_SESSION['otp']);
                        unset($_SESSION['otp_expiry']);
                        echo json_encode(['status' => true, 'message' => 'OTP verified successfully']);
                    } else {
                        echo json_encode(['status' => false, 'message' => 'Invalid OTP']);
                    }
                } else {
                    echo json_encode(['status' => false, 'message' => 'OTP expired or not found']);
                }
                break;
            default:
                echo json_encode(['status' => false, 'message' => 'Invalid action']);
        }
    }
    exit;  
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
<link rel="stylesheet" href="styles.css"></head>
<body>
    <div class="container">
        <h2>Email Verification</h2>
        <div class="otp-verification">
            <label for="email">VIT Email Address</label>
            <input type="email" id="email" name="email" placeholder="yourname@vit.edu" required><br><br>
            <div class="otp-input-group" style="display: none;">
                <input type="text" id="otp" placeholder="Enter 6-digit OTP" maxlength="6"><br><br>
                <button type="button" id="verifyBtn">Verify OTP</button>
            </div>
            <button type="button" id="sendOtpBtn">Send OTP</button>
            <span id="resendTimer" style="display:none;"></span><br><br>
            <div id="otpStatus" class="verification-status"></div>
        </div>
    </div>

    <script>
        const emailInput = document.getElementById('email');
        const otpInput = document.getElementById('otp');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const verifyBtn = document.getElementById('verifyBtn');
        const otpStatus = document.getElementById('otpStatus');
        const resendTimer = document.getElementById('resendTimer');
        const otpInputGroup = document.querySelector('.otp-input-group');


        function startResendTimer() {
            let seconds = 30;
            sendOtpBtn.style.display = 'none';
            resendTimer.style.display = 'inline';
            resendTimer.textContent = `(${seconds}s)`;

            const timer = setInterval(() => {
                seconds--;
                resendTimer.textContent = `(${seconds}s)`;
                if (seconds < 0) {
                    clearInterval(timer);
                    resendTimer.style.display = 'none';
                    sendOtpBtn.style.display = 'inline-block';
                    sendOtpBtn.textContent = 'Resend OTP';
                }
            }, 1000);
        }

        sendOtpBtn.addEventListener('click', () => {
            const email = emailInput.value;
            if (!email.endsWith('@vit.edu')) {
                otpStatus.textContent = 'Please enter a valid VIT email address';
                otpStatus.className = 'verification-status error';
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'send_otp', email: email })
            })
            .then(response => response.json())
            .then(data => {
                otpStatus.textContent = data.message;
                otpStatus.className = data.status ? 'verification-status success' : 'verification-status error';
                if (data.status) {
                    startResendTimer();
                    otpInputGroup.style.display = 'flex'; 
                    sendOtpBtn.style.display = 'none';
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                otpStatus.textContent = "An error occurred. Please try again later.";
                otpStatus.className = 'verification-status error';
            });
        });

        otpInput.addEventListener('input', () => {
            let otp = otpInput.value;
            otp = otp.replace(/\D/g, '').trim();
            otpInput.value = otp;

            if (otp.length === 6) {
                const email = emailInput.value;
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'verify_otp', otp: otp, email: email })
                })
                .then(response => response.json())
                .then(data => {
                    otpStatus.textContent = data.message;
                    otpStatus.className = data.status ? 'verification-status success' : 'verification-status error';
                    if(data.status){
                        otpInput.disabled = true;
                        emailInput.disabled = true;
                        sendOtpBtn.style.display = 'none';
                        resendTimer.style.display = 'none';
                        otpInputGroup.style.display = 'none';
                    } else {
                        otpInput.classList.add('error');
                        otpInput.classList.add('error-shake');
                        setTimeout(() => otpInput.classList.remove('error-shake'), 500);
                    }
                })
                .catch(error => {
                    console.error("Fetch error:", error);
                    otpStatus.textContent = "An error occurred. Please try again later.";
                    otpStatus.className = 'verification-status error';
                });
            } else {
                otpInput.classList.remove('error');
            }
        });
    </script>
</body>
</html>