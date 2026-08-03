// FBCA LMS - Main JavaScript File

// Utility function for AJAX requests
async function makeRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('Request failed:', error);
        return { success: false, message: error.message };
    }
}

// Quiz timer functionality
function startQuizTimer(timeLimit, callback) {
    if (timeLimit <= 0) return null;
    
    let timeLeft = timeLimit * 60; // Convert to seconds
    const timerElement = document.getElementById('quiz-timer');
    
    const timer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        if (timerElement) {
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            if (callback) callback();
        }
        
        timeLeft--;
    }, 1000);
    
    return timer;
}

// Quiz submission
function submitQuiz(quizId, lessonId) {
    const form = document.getElementById('quiz-form');
    if (!form) return;
    
    const formData = new FormData(form);
    const answers = {};
    
    formData.forEach((value, key) => {
        if (key.startsWith('question_')) {
            answers[key] = value;
        }
    });
    
    // Validate all questions answered
    const totalQuestions = document.querySelectorAll('.question-card').length;
    if (Object.keys(answers).length < totalQuestions) {
        if (!confirm('You have not answered all questions. Do you want to submit anyway?')) {
            return;
        }
    }
    
    fetch('../api/submit-quiz.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            quiz_id: quizId,
            lesson_id: lessonId,
            answers: answers
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `quiz-result.php?lesson_id=${lessonId}&score_id=${data.score_id}`;
        } else {
            alert('Error: ' + (data.message || 'Failed to submit quiz'));
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Auto-save quiz progress (optional)
function autoSaveQuiz(quizId, answers) {
    localStorage.setItem(`quiz_${quizId}`, JSON.stringify(answers));
}

function loadQuizProgress(quizId) {
    const saved = localStorage.getItem(`quiz_${quizId}`);
    if (saved) {
        const answers = JSON.parse(saved);
        Object.keys(answers).forEach(key => {
            const input = document.querySelector(`input[name="${key}"][value="${answers[key]}"]`);
            if (input) input.checked = true;
        });
    }
}

// Progress bar animation
function animateProgressBar(elementId, targetPercent) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    let current = 0;
    const increment = targetPercent / 100;
    const timer = setInterval(() => {
        current += increment;
        if (current >= targetPercent) {
            current = targetPercent;
            clearInterval(timer);
        }
        element.style.width = current + '%';
        element.textContent = Math.round(current) + '%';
    }, 20);
}

// UI: Mobile nav toggle
function initMobileNav() {
    const toggle = document.getElementById('nav-toggle');
    const menu = document.getElementById('nav-menu');
    const overlay = document.getElementById('nav-overlay');

    function openMenu() {
        if (menu) menu.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        if (toggle && toggle.querySelector('i')) {
            toggle.querySelector('i').className = 'fas fa-times';
        }
    }

    function closeMenu() {
        if (menu) menu.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
        if (toggle && toggle.querySelector('i')) {
            toggle.querySelector('i').className = 'fas fa-bars';
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function() {
            if (menu && menu.classList.contains('open')) closeMenu();
            else openMenu();
        });
    }
    if (overlay) overlay.addEventListener('click', closeMenu);

    // Close menu when a nav link is clicked (for SPA-like feel on same domain)
    if (menu) {
        menu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) closeMenu();
            });
        });
    }
}

// UI: User dropdown
function initUserDropdown() {
    const trigger = document.getElementById('user-dropdown-trigger');
    const dropdown = document.getElementById('user-dropdown');
    if (!trigger || !dropdown) return;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
        trigger.setAttribute('aria-expanded', dropdown.classList.contains('show'));
    });

    document.addEventListener('click', function() {
        dropdown.classList.remove('show');
        trigger.setAttribute('aria-expanded', 'false');
    });

    dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// UI: Fade-in on scroll for lesson cards only (dashboard cards stay visible)
function initScrollAnimations() {
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -30px 0px', threshold: 0 });

    document.querySelectorAll('.lesson-card').forEach(function(el) {
        el.style.opacity = '0';
        observer.observe(el);
    });
}

// UI: Set active nav link based on current URL
function setActiveNavLink() {
    const path = window.location.pathname;
    document.querySelectorAll('.nav-menu a[href]').forEach(function(link) {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;
        try {
            const url = new URL(link.href);
            if (url.pathname === path || (path.indexOf(url.pathname) === 0 && url.pathname.length > 1)) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        } catch (e) {}
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initMobileNav();
    initUserDropdown();
    setActiveNavLink();
    initScrollAnimations();

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Form validation
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});
