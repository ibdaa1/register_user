// Basic JS for login and register (works for web)
// Paths assume this JS is loaded from htdocs/frontend/login.html
console.log('login.js loaded');

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const messageBox = document.getElementById('message');

    function showMessage(msg) {
        if (messageBox) messageBox.textContent = msg;
        console.log('MESSAGE:', msg);
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            showMessage('جاري تسجيل الدخول...');
            const form = new FormData(loginForm);
            const payload = {
                identifier: form.get('identifier'),
                password: form.get('password'),
            };
            try {
                const res = await fetch('/api/users/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    credentials: 'include'
                });
                const data = await res.json();
                console.log('login response', res.status, data);
                if (data.success) {
                    showMessage('تم تسجيل الدخول بنجاح');
                    localStorage.setItem('auth_token', data.token);
                } else {
                    showMessage(data.message || 'خطأ في تسجيل الدخول');
                }
            } catch (err) {
                showMessage('خطأ في الاتصال بالخادم');
                console.error('login error', err);
            }
        });
    } else {
        console.warn('loginForm not found');
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            showMessage('جاري التسجيل...');
            const formData = new FormData(registerForm);

            try {
                const res = await fetch('/api/users/register_user.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                    // IMPORTANT: Do NOT set Content-Type header for FormData (browser sets boundary)
                });

                // Log request/response for debugging
                console.log('register response status', res.status);
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Failed to parse JSON response:', text);
                    showMessage('استجابة غير متوقعة من الخادم');
                    return;
                }
                console.log('register response', data);

                if (data.success) {
                    showMessage('تم التسجيل بنجاح. يمكنك الآن تسجيل الدخول.');
                    registerForm.reset();
                } else {
                    showMessage(data.message || 'فشل التسجيل');
                }
            } catch (err) {
                showMessage('خطأ في الاتصال بالخادم');
                console.error('register error', err);
            }
        });
    } else {
        console.warn('registerForm not found');
    }
});