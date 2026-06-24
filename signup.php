<?php require_once 'includes/security.php'; ?>
<?php
$pageTitle = "Register";
include 'includes/header.php';
?>

    <style>
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e1e5ee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin: 0 15px;
            position: relative;
            transition: var(--transition-smooth);
        }

        .step.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 0 0 5px rgba(31, 60, 136, 0.1);
        }

        .step.completed {
            background: var(--accent-gold);
            color: var(--dark-text);
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 2px;
            background: #e1e5ee;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
        }

        .role-card {
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid transparent;
        }

        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .role-card.selected {
            border-color: var(--accent-gold);
            background-color: rgba(31, 60, 136, 0.05);
        }

        #school_search_results {
            position: absolute;
            width: 100%;
            z-index: 1000;
            display: none;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
        }

        #school_search_results .result-item {
            padding: 10px;
            cursor: pointer;
        }

        #school_search_results .result-item:hover {
            background-color: #f8f9fa;
        }
    </style>

    <div class="auth-wrapper py-5">
        <div class="auth-card glass-card reveal reveal-up" style="max-width: 800px;">
            <div class="text-center mb-5">
                <a class="navbar-brand" href="index.php">
                    <img src="<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks Logo" style="max-height: 40px;">
                </a>
                <h4 class="mt-3">Create Your Account</h4>
                <p class="text-muted">Join the world's leading school portal.</p>
            </div>

            <div class="step-indicator">
                <div class="step active" id="step1-btn">1</div>
                <div class="step" id="step2-btn">2</div>
                <div class="step" id="step3-btn">3</div>
            </div>

            <form id="registrationForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
                <!-- Step 1: Personal Information -->
                <div id="step1">
                    <h5 class="mb-4 text-center">Step 1: Your Personal Details</h5>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user input-icon-box"></i></span>
                                <input type="text" class="form-control" name="full_name" placeholder="John Doe" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope input-icon-box"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="john@example.com" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone input-icon-box"></i></span>
                                <input type="tel" class="form-control" name="phone" placeholder="+234..." required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Create Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock input-icon-box"></i></span>
                                <input type="password" class="form-control" name="password" id="password" required placeholder="••••••••">
                                <span class="input-group-text password-toggle" data-target="password">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 d-flex justify-content-end">
                        <button type="button" class="btn btn-gold px-5 py-3" onclick="nextStep(1)">Next: Choose Role <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <!-- Step 2: Role Selection -->
                <div id="step2" style="display: none;">
                    <h5 class="mb-4 text-center">Step 2: Choose Your Role</h5>
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-6">
                            <div class="feature-card role-card text-center p-4" onclick="selectRole('owner')">
                                <div class="icon-box mx-auto mb-3"><i class="fas fa-school"></i></div>
                                <h4>School Owner</h4>
                                <p class="text-muted small">Register and manage one or more schools.</p>
                                <input type="radio" name="role" value="owner" id="role_owner" class="d-none" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card role-card text-center p-4" onclick="selectRole('staff')">
                                <div class="icon-box mx-auto mb-3"><i class="fas fa-user-tie"></i></div>
                                <h4>School Staff</h4>
                                <p class="text-muted small">Join an existing school to manage its activities.</p>
                                <input type="radio" name="role" value="staff" id="role_staff" class="d-none" required>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary px-4 py-2" onclick="prevStep(2)">Back</button>
                        <button type="button" class="btn btn-gold px-5 py-3" onclick="nextStep(2)">Next: Complete Setup <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <!-- Step 3: Setup (Owner) -->
                <div id="setup-owner" style="display: none;">
                    <h5 class="mb-4 text-center">Step 3: Register Your First School</h5>
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Official School Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-university input-icon-box"></i></span>
                                <input type="text" class="form-control" name="school_name" placeholder="EduRemarks Academy">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">School Type</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-graduation-cap input-icon-box"></i></span>
                                <select class="form-select" name="school_type">
                                    <option selected disabled value="">Choose Type...</option>
                                    <option>Nursery & Primary</option>
                                    <option>Secondary / High School</option>
                                    <option>Tertiary / Vocational</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt input-icon-box"></i></span>
                                <input type="text" class="form-control" name="school_address" placeholder="123 Education Way">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Setup (Staff) -->
                <div id="setup-staff" style="display: none;">
                    <h5 class="mb-4 text-center">Step 3: Join Your School</h5>
                    <div class="row g-4">
                        <div class="col-md-12 position-relative">
                            <label class="form-label">Search for School Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="school_search" placeholder="Type school name..." autocomplete="off">
                            </div>
                            <div id="school_search_results" class="shadow"></div>
                            <input type="hidden" name="school_id" id="selected_school_id">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Enter School Unique ID</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card input-icon-box"></i></span>
                                <input type="text" class="form-control" name="unique_school_id" id="unique_school_id" placeholder="e.g., ER445324QR">
                            </div>
                            <p class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i> Ask your school administrator for this code.</p>
                        </div>
                    </div>
                </div>

                <div id="step3-controls" style="display: none;">
                    <div class="mt-5 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary px-4 py-2" onclick="prevStep(3)">Back</button>
                        <button type="submit" class="btn btn-gold px-5 py-3" id="submitBtn">Finalize Registration</button>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <p class="mb-0 text-muted small">Already part of EduRemarks? <a href="login.php" class="text-decoration-none" style="color: var(--secondary-blue);">Login here</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Professional Submission Overlay -->
    <div id="process-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; flex-direction: column;">
        <div class="loader-visual" style="position: relative; width: 80px; height: 80px; margin-bottom: 25px;">
            <div class="spinner-ring" style="position: absolute; width: 100%; height: 100%; border: 4px solid rgba(244, 180, 0, 0.1); border-top: 4px solid #F4B400; border-radius: 50%; animation: auth-spin 1s linear infinite;"></div>
            <div class="spinner-core" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 12px; height: 12px; background: #F4B400; border-radius: 50%; box-shadow: 0 0 15px #F4B400;"></div>
        </div>
        <div class="loader-message text-center">
            <h5 class="text-white fw-900 mb-2 uppercase tracking-2" style="font-size: 0.9rem; letter-spacing: 3px;">PROCESSING...</h5>
            <p class="text-white opacity-50 tiny-text uppercase tracking-1" style="font-size: 0.65rem;">Configuring Institutional Node</p>
        </div>
    </div>

    <style>
    @keyframes auth-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>

    <!-- Alert Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card">
                <div class="modal-body text-center p-5">
                    <div id="modalIcon" class="mb-4" style="font-size: 4rem;"></div>
                    <h4 id="modalTitle" class="mb-3"></h4>
                    <p id="modalMessage" class="text-muted mb-4"></p>
                    <button type="button" class="btn btn-gold px-5" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let selectedRole = '';

        function selectRole(role) {
            selectedRole = role;
            document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('role_' + role).checked = true;
            event.currentTarget.classList.add('selected');
        }

        function nextStep(step) {
            if (step === 1) {
                const inputs = document.getElementById('step1').querySelectorAll('input[required]');
                let valid = true;
                inputs.forEach(i => {
                    if (!i.value) { valid = false; i.classList.add('is-invalid'); }
                    else { i.classList.remove('is-invalid'); }
                });
                if (!valid) return;
            }
            
            if (step === 2 && !selectedRole) {
                Notif.show('Please select a role to continue.', 'warning');
                return;
            }

            document.getElementById('step' + step).style.display = 'none';
            document.getElementById('step' + step + '-btn').classList.add('completed');
            
            currentStep = step + 1;
            document.getElementById('step' + currentStep + '-btn').classList.add('active');
            
            if (currentStep === 3) {
                document.getElementById('step3-controls').style.display = 'block';
                if (selectedRole === 'owner') {
                    document.getElementById('setup-owner').style.display = 'block';
                    document.getElementById('setup-staff').style.display = 'none';
                } else {
                    document.getElementById('setup-staff').style.display = 'block';
                    document.getElementById('setup-owner').style.display = 'none';
                }
            } else {
                document.getElementById('step' + currentStep).style.display = 'block';
            }
        }

        function prevStep(step) {
            if (step === 2) {
                document.getElementById('step2').style.display = 'none';
                document.getElementById('step1').style.display = 'block';
                document.getElementById('step1-btn').classList.remove('completed');
                document.getElementById('step2-btn').classList.remove('active');
            } else if (step === 3) {
                document.getElementById('setup-owner').style.display = 'none';
                document.getElementById('setup-staff').style.display = 'none';
                document.getElementById('step3-controls').style.display = 'none';
                document.getElementById('step2').style.display = 'block';
                document.getElementById('step2-btn').classList.remove('completed');
                document.getElementById('step3-btn').classList.remove('active');
            }
            currentStep = step - 1;
        }

        // School Search Logic
        const schoolSearch = document.getElementById('school_search');
        const searchResults = document.getElementById('school_search_results');
        
        schoolSearch.addEventListener('input', function() {
            const query = this.value;
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            fetch(`ajax/get_schools.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.schools.length > 0) {
                        searchResults.innerHTML = '';
                        data.schools.forEach(school => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.innerHTML = `<strong>${school.school_name}</strong> <span class="text-muted ms-2">(${school.unique_id.substring(0,4)}***)</span>`;
                            div.onclick = () => {
                                schoolSearch.value = school.school_name;
                                document.getElementById('selected_school_id').value = school.id;
                                searchResults.style.display = 'none';
                            };
                            searchResults.appendChild(div);
                        });
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.style.display = 'none';
                    }
                });
        });

        document.addEventListener('click', (e) => {
            if (!schoolSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        // AJAX Submission
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            const overlay = document.getElementById('process-overlay');
            
            overlay.style.display = 'flex';
            submitBtn.disabled = true;

            const formData = new FormData(this);
            
            fetch('ajax/register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
                .then(data => {
                    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
                    const icon = document.getElementById('modalIcon');
                    const title = document.getElementById('modalTitle');
                    const msg = document.getElementById('modalMessage');

                    overlay.style.display = 'none';

                    if (data.success) {
                        icon.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                        title.innerText = 'Success!';
                        msg.innerText = data.message;
                        document.getElementById('registrationForm').reset();
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 5000);
                    } else {
                        icon.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i>';
                        title.innerText = 'Registration Failed';
                        msg.innerText = data.message;
                    }
                    modal.show();
                })
                .catch(error => {
                    overlay.style.display = 'none';
                    console.error('Error:', error);
                    Notif.show('A system error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
    </script>

<?php include 'includes/footer.php'; ?>
