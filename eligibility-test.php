<?php
$page_title = "Visa Eligibility Test | Visafy";
include('includes/header.php');

// Include database connection
require_once 'config/db_connect.php';

// Get the first question to start the test
try {
    $stmt = $conn->prepare("
        SELECT q.*, c.name as category_name 
        FROM decision_tree_questions q 
        LEFT JOIN decision_tree_categories c ON q.category_id = c.id 
        WHERE q.is_active = 1 
        ORDER BY q.id ASC 
        LIMIT 1
    ");
    $stmt->execute();
    $first_question = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error loading initial question: " . $e->getMessage();
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">
                 Free Visa Eligibility Check
            </h1>
            <p class="hero-subtitle">Answer a few simple questions to check your visa eligibility and get instant results</p>
        </div>
    </div>
</section>

<!-- Eligibility Test Section -->
<section class="section eligibility-test">
    <div class="container">
        <div class="test-container">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Eligibility Assessment</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php elseif ($first_question): ?>
                        <div id="question-section">
                            <div class="question-container">
                                <h3 id="question-text"><?php echo htmlspecialchars($first_question['question_text']); ?></h3>
                                <?php if (isset($first_question['description']) && $first_question['description']): ?>
                                    <p class="question-description"><?php echo htmlspecialchars($first_question['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div id="options-container" class="options-container">
                                <div class="loader">
                                    <i class="fas fa-circle-notch fa-spin"></i> Loading options...
                                </div>
                            </div>
                        </div>

                        <div id="result-section" class="result-section" style="display: none;">
                            <div class="result-container">
                                <div id="result-icon"></div>
                                <h3 id="result-title"></h3>
                                <p id="result-message"></p>
                                <div class="cta-container">
                                    <p class="cta-text">Want to proceed with your visa application?</p>
                                    <div class="cta-buttons">
                                        <a href="register.php" class="btn btn-primary">
                                            <i class="fas fa-user-plus"></i> Create Free Account
                                        </a>
                                        <a href="consultation.php" class="btn btn-secondary">
                                            <i class="fas fa-comments"></i> Book Consultation
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="result-actions">
                                <button id="restart-test" class="btn btn-outline">
                                    <i class="fas fa-redo"></i> Take Test Again
                                </button>
                                <a href="services.php" class="btn btn-link">
                                    <i class="fas fa-arrow-right"></i> Explore Our Services
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Our eligibility test is currently being updated. Please check back later or contact us directly.</p>
                            <div class="empty-state-actions">
                                <a href="contact.php" class="btn btn-primary">Contact Us</a>
                                <a href="index.php" class="btn btn-secondary">Back to Home</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Hero Section Styles */
.hero {
    padding: 6rem 0;
    background-color: var(--white);
    color: var(--text-color);
    overflow: hidden;
    position: relative;
}

.hero-content {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
}

.hero-title {
    font-size: 3.5rem;
    color: var(--dark-blue);
    font-weight: 700;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    line-height: 1.6;
    color: var(--text-light);
}

/* Test Section Styles */
.eligibility-test {
    padding: 6rem 0;
    background-color: var(--background-light);
}

.test-container {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.card-header {
    padding: 2rem 2.5rem;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--white);
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

.card-header h5 {
    margin: 0;
    color: var(--dark-blue);
    font-size: 1.4rem;
    font-weight: 600;
}

.card-body {
    padding: 3rem 2.5rem;
}

.question-container {
    text-align: center;
    margin-bottom: 3rem;
}

.question-container h3 {
    color: var(--dark-blue);
    font-size: 1.6rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

.question-description {
    color: var(--text-light);
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    line-height: 1.6;
}

.options-container {
    display: grid;
    gap: 1rem;
}

.option-button {
    padding: 1.25rem 1.5rem;
    background: var(--white);
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    text-align: left;
    font-size: 1rem;
    color: var(--dark-blue);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.option-button:hover {
    border-color: var(--primary-color);
    background-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.option-button:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.result-section {
    text-align: center;
}

.result-container {
    margin-bottom: 3rem;
}

.result-container i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
}

.result-container.eligible i {
    color: var(--success-color);
}

.result-container.not-eligible i {
    color: var(--danger-color);
}

.cta-container {
    margin-top: 2rem;
    padding: 2rem;
    background-color: var(--background-light);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.cta-text {
    font-size: 1.2rem;
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    padding: 1rem 2rem;
    border-radius: var(--border-radius);
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-hover);
    border-color: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background-color: var(--dark-blue);
    color: var(--white);
    border-color: var(--dark-blue);
}

.btn-secondary:hover {
    background-color: #0a3d5a;
    border-color: #0a3d5a;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-outline {
    background-color: transparent;
    border-color: var(--dark-blue);
    color: var(--dark-blue);
}

.btn-outline:hover {
    background-color: var(--dark-blue);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary-color);
    text-decoration: none;
    padding: 0.75rem 1rem;
}

.btn-link:hover {
    color: var(--primary-hover);
    text-decoration: underline;
    transform: translateY(-1px);
}

.result-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.loader {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

.loader i {
    margin-right: 0.5rem;
    font-size: 1.5rem;
    color: var(--primary-color);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-light);
    margin-bottom: 1.5rem;
}

.empty-state p {
    color: var(--text-light);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.empty-state-actions {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: var(--border-radius);
    border-left: 4px solid;
}

.alert-danger {
    background-color: #fef2f2;
    border-color: var(--danger-color);
    color: #991b1b;
}

.alert-success {
    background-color: #f0fdf4;
    border-color: var(--success-color);
    color: #166534;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .hero-title {
        font-size: 3rem;
    }
    
    .card-body {
        padding: 2.5rem 2rem;
    }
    
    .card-header {
        padding: 1.5rem 2rem;
    }
}

@media (max-width: 768px) {
    .hero {
        padding: 4rem 0;
    }
    
    .eligibility-test {
        padding: 4rem 0;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .card-body {
        padding: 2rem 1.5rem;
    }
    
    .card-header {
        padding: 1.25rem 1.5rem;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .empty-state-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
    
    .result-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .result-actions .btn {
        width: 100%;
        max-width: 300px;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-subtitle {
        font-size: 1rem;
    }
    
    .card-body {
        padding: 1.5rem 1rem;
    }
    
    .card-header {
        padding: 1rem 1rem;
    }
    
    .question-container h3 {
        font-size: 1.4rem;
    }
    
    .option-button {
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
    }
    
    .cta-container {
        padding: 1.5rem 1rem;
    }
    
    .cta-text {
        font-size: 1.1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionSection = document.getElementById('question-section');
    const resultSection = document.getElementById('result-section');
    const questionText = document.getElementById('question-text');
    const optionsContainer = document.getElementById('options-container');
    const resultIcon = document.getElementById('result-icon');
    const resultTitle = document.getElementById('result-title');
    const resultMessage = document.getElementById('result-message');
    const restartButton = document.getElementById('restart-test');
    
    let currentQuestionId = <?php echo $first_question ? $first_question['id'] : 'null'; ?>;
    let assessmentId = null;
    
    // Debug logging
    console.log('Initial question ID:', currentQuestionId);
    
    // Function to load question options
    async function loadOptions(questionId) {
        try {
            console.log('Loading options for question:', questionId);
            optionsContainer.innerHTML = '<div class="loader"><i class="fas fa-circle-notch fa-spin"></i> Loading options...</div>';
            
            const response = await fetch(`ajax/get_options.php?question_id=${questionId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const options = await response.json();
            console.log('Loaded options:', options);
            
            if (Array.isArray(options) && options.length > 0) {
                optionsContainer.innerHTML = options.map(option => `
                    <button class="option-button" data-id="${option.id}" 
                            data-is-endpoint="${option.is_endpoint}" 
                            data-next-question="${option.next_question_id || ''}" 
                            data-endpoint-eligible="${option.endpoint_eligible || false}"
                            data-endpoint-result="${option.endpoint_result || ''}">
                        ${option.option_text}
                    </button>
                `).join('');
                
                // Add click handlers to options
                document.querySelectorAll('.option-button').forEach(button => {
                    button.addEventListener('click', handleOptionClick);
                });
            } else {
                throw new Error('No options returned from server');
            }
            
        } catch (error) {
            console.error('Error loading options:', error);
            optionsContainer.innerHTML = `
                <div class="alert alert-danger">
                    Error loading options. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to handle option selection
    async function handleOptionClick(event) {
        try {
            const button = event.currentTarget;
            const optionId = button.dataset.id;
            const isEndpoint = button.dataset.isEndpoint === 'true';
            const nextQuestionId = button.dataset.nextQuestion;
            const endpointEligible = button.dataset.endpointEligible === 'true';
            const endpointResult = button.dataset.endpointResult;
            
            console.log('Option clicked:', {
                optionId,
                isEndpoint,
                nextQuestionId,
                endpointEligible,
                endpointResult
            });
            
            // Start assessment if not already started
            if (!assessmentId) {
                const response = await fetch('ajax/start_assessment.php', {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    assessmentId = data.assessment_id;
                    console.log('Assessment started:', assessmentId);
                }
            }
            
            // Save the answer
            const saveResponse = await fetch('ajax/save_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    assessment_id: assessmentId,
                    question_id: currentQuestionId,
                    option_id: optionId
                })
            });
            
            const saveResult = await saveResponse.json();
            console.log('Save answer result:', saveResult);
            
            if (isEndpoint) {
                showResult(endpointEligible, endpointResult);
            } else if (nextQuestionId) {
                await loadQuestion(nextQuestionId);
            }
            
        } catch (error) {
            console.error('Error processing answer:', error);
            optionsContainer.innerHTML = `
                <div class="alert alert-danger">
                    Error processing your answer. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to load question
    async function loadQuestion(questionId) {
        try {
            console.log('Loading question:', questionId);
            const response = await fetch(`ajax/get_question.php?id=${questionId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const question = await response.json();
            console.log('Loaded question:', question);
            
            if (question.success) {
                currentQuestionId = question.id;
                questionText.textContent = question.question_text;
                
                const descriptionElement = document.querySelector('.question-description');
                if (descriptionElement) {
                    if (question.description) {
                        descriptionElement.textContent = question.description;
                        descriptionElement.style.display = 'block';
                    } else {
                        descriptionElement.style.display = 'none';
                    }
                }
                
                await loadOptions(questionId);
            } else {
                throw new Error(question.message || 'Failed to load question');
            }
            
        } catch (error) {
            console.error('Error loading question:', error);
            questionSection.innerHTML = `
                <div class="alert alert-danger">
                    Error loading question. Please try again.<br>
                    <small>${error.message}</small>
                </div>`;
        }
    }
    
    // Function to show result
    function showResult(isEligible, message) {
        questionSection.style.display = 'none';
        resultSection.style.display = 'block';
        
        resultIcon.innerHTML = isEligible ? 
            '<i class="fas fa-check-circle"></i>' : 
            '<i class="fas fa-times-circle"></i>';
        
        resultTitle.textContent = isEligible ? 
            'You may be eligible!' : 
            'You may not be eligible';
        
        resultMessage.textContent = message;
        
        resultSection.querySelector('.result-container').className = 
            'result-container ' + (isEligible ? 'eligible' : 'not-eligible');
    }
    
    // Handle restart button
    restartButton.addEventListener('click', function() {
        location.reload();
    });
    
    // Load initial options if we have a question
    if (currentQuestionId) {
        console.log('Loading initial options...');
        loadOptions(currentQuestionId);
    }
});
</script>

<?php include('includes/footer.php'); ?>
