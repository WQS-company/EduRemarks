<?php
$pageTitle = "FAQ";
include 'includes/header.php';
?>

    <!-- Page Header -->
    <section class="hero-section reveal reveal-up" style="padding: 100px 0 60px;">
        <div class="container text-center">
            <h1 class="hero-title" style="font-size: 2.8rem;">Frequently Asked Questions</h1>
            <p class="hero-subtitle">Find answers to common questions about EduRemarks.</p>
        </div>
    </section>

    <!-- FAQ Content -->
    <section class="reveal reveal-up">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion faq-accordion" id="faqAccordion">
                        <!-- Item 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq1">
                                    How long does it take to set up?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Setting up your school on EduRemarks is incredibly fast. Once registered, you can
                                    start importing student data and managing results in under 24 hours. Our dedicated
                                    support team is also available to assist with onboarding.
                                </div>
                            </div>
                        </div>
                        <!-- Item 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq2">
                                    Is my school data secure?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Absolutely. We use industry-standard bank-grade encryption and secure cloud servers
                                    to ensure that all student records, financial transactions, and staff data remain
                                    confidential and protected at all times.
                                </div>
                            </div>
                        </div>
                        <!-- Item 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq3">
                                    Can parents see results online?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! EduRemarks features a parent portal where parents can securely log in to view
                                    their children's academic performance, track attendance, and make school fee
                                    payments directly.
                                </div>
                            </div>
                        </div>
                        <!-- Item 4 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq4">
                                    What payment methods are supported?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We support direct bank transfers, credit/debit cards, and our own integrated wallet
                                    system. Payments are processed instantly, and schools receive automated alerts for
                                    every transaction.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
