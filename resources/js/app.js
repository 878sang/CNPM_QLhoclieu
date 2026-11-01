import 'bootstrap';
import '../css/app.css';

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.setAttribute('aria-label', isPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        button.innerHTML = `<i class="bi bi-eye${isPassword ? '-slash' : ''}" aria-hidden="true"></i>`;
    });
});

document.querySelectorAll('[data-password-strength]').forEach((input) => {
    input.addEventListener('input', () => {
        const value = input.value;
        const score = Number(value.length >= 8) + Number(/[A-Z]/.test(value)) + Number(/[0-9]/.test(value)) + Number(/[^A-Za-z0-9]/.test(value));
        const bar = document.querySelector(input.dataset.passwordStrength);
        const label = document.querySelector(`${input.dataset.passwordStrength}-label`);
        const labels = ['Chưa có', 'Yếu', 'Vừa', 'Tốt', 'Mạnh'];
        bar.style.width = `${score * 25}%`;
        bar.className = `progress-bar strength-${score}`;
        label.textContent = labels[score];
    });
});

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        localStorage.setItem('learnmate-theme', nextTheme);
        button.setAttribute('aria-pressed', nextTheme === 'dark');
    });
});

const savedTheme = localStorage.getItem('learnmate-theme');
if (savedTheme) document.documentElement.dataset.theme = savedTheme;
