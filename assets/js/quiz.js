// Quiz-specific JavaScript functions

// Start quiz timer
function startQuizTimer(timeLimit, callback) {
    if (timeLimit <= 0) return null;
    
    let timeLeft = timeLimit * 60; // Convert to seconds
    const timerElement = document.getElementById('quiz-timer');
    
    const timer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        if (timerElement) {
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Change color when time is running out
            if (timeLeft <= 60) {
                timerElement.style.color = 'var(--danger-color)';
            }
        }
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            if (callback) callback();
        }
        
        timeLeft--;
    }, 1000);
    
    return timer;
}

// Submit quiz
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
    
    if (!confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.')) {
        return;
    }
    
    // Show loading
    const submitBtn = document.querySelector('button[onclick*="submitQuiz"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
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
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Quiz';
            }
        }
    })
    .catch(error => {
        alert('Error: ' + error);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Quiz';
        }
    });
}

// Auto-save quiz progress
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

// Highlight selected option
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="radio"]').forEach(input => {
        input.addEventListener('change', function() {
            // Remove selected class from all options in this question
            const questionCard = this.closest('.question-card');
            questionCard.querySelectorAll('.option-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Add selected class to parent option item
            const optionItem = this.closest('.option-item');
            if (optionItem) {
                optionItem.classList.add('selected');
            }
        });
    });
});
