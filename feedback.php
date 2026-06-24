<?php
$pageTitle = "Feedback";
include 'includes/header.php';
?>

    <section class="hero-section reveal reveal-up" style="padding: 100px 0 60px;">
        <div class="container text-center">
            <h1 class="hero-title">Your Feedback Matters</h1>
            <p class="hero-subtitle">Help us build the perfect platform for your school.</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="glass-card p-4 p-md-5 reveal reveal-up stagger-1">
                        <form action="#">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <input type="text" class="form-control px-4 py-3" placeholder="Enter your name"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" class="form-control px-4 py-3" placeholder="Enter your email"
                                        required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Feedback Subject</label>
                                    <select class="form-select px-4 py-3">
                                        <option selected>General Feedback</option>
                                        <option>Feature Request</option>
                                        <option>Bug Report</option>
                                        <option>User Experience</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Share your thoughts</label>
                                    <textarea class="form-control px-4 py-3" rows="5"
                                        placeholder="What can we improve?"></textarea>
                                </div>
                                <div class="col-12 text-center mt-5">
                                    <button type="submit" class="btn btn-premium-gold px-5 py-3">Send Feedback</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
