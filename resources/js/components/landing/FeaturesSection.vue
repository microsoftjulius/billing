<template>
  <section id="featured-departments" class="featured-departments section">

    <!-- Section Title -->
    <div class="container section-title" data-aos="fade-up">
      <h2>Featured Departments</h2>
      <p>Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">

      <div class="row g-5">

        <!-- Specialty Cards -->
        <div class="col-lg-6" data-aos="zoom-in" data-aos-delay="100">
          <div class="specialty-card">
            <div class="specialty-content">
              <div class="specialty-meta">
                <span class="specialty-label">Specialized Care</span>
              </div>
              <h3>Cardiovascular Medicine</h3>
              <p>Advanced diagnostic imaging and interventional procedures for comprehensive heart health management
                with personalized treatment protocols.</p>
              <div class="specialty-features">
                <span><i class="bi bi-check-circle-fill"></i>24/7 Emergency Cardiac Care</span>
                <span><i class="bi bi-check-circle-fill"></i>Minimally Invasive Procedures</span>
              </div>
              <a href="department-details.html" class="specialty-link">
                Explore Cardiology <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="specialty-visual">
              <img :src="cardiologyImage" alt="Cardiovascular Medicine" class="img-fluid">
              <div class="visual-overlay">
                <i class="bi bi-heart-pulse"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6" data-aos="zoom-in" data-aos-delay="200">
          <div class="specialty-card">
            <div class="specialty-content">
              <div class="specialty-meta">
                <span class="specialty-label">Expert Care</span>
              </div>
              <h3>Neurological Sciences</h3>
              <p>Cutting-edge neuroimaging and neurosurgical expertise for complex brain and spinal cord conditions
                with innovative treatment approaches.</p>
              <div class="specialty-features">
                <span><i class="bi bi-check-circle-fill"></i>Advanced Brain Imaging</span>
                <span><i class="bi bi-check-circle-fill"></i>Robotic Surgery</span>
              </div>
              <a href="department-details.html" class="specialty-link">
                Explore Neurology <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="specialty-visual">
              <img :src="neurologyImage" alt="Neurological Sciences" class="img-fluid">
              <div class="visual-overlay">
                <i class="bi bi-cpu"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Department Highlights -->
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
          <div class="department-highlight">
            <div class="highlight-icon">
              <i class="bi bi-shield-plus"></i>
            </div>
            <h4>Orthopedic Surgery</h4>
            <p>Comprehensive musculoskeletal care utilizing advanced arthroscopic techniques and joint replacement
              procedures.</p>
            <ul class="highlight-list">
              <li>Sports Medicine</li>
              <li>Joint Replacement</li>
              <li>Spine Surgery</li>
            </ul>
            <a href="department-details.html" class="highlight-cta">Learn More</a>
          </div>
        </div>

        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
          <div class="department-highlight">
            <div class="highlight-icon">
              <i class="bi bi-people"></i>
            </div>
            <h4>Pediatric Care</h4>
            <p>Child-centered healthcare services from newborn to adolescence with family-focused treatment
              approaches.</p>
            <ul class="highlight-list">
              <li>Neonatal Intensive Care</li>
              <li>Developmental Pediatrics</li>
              <li>Pediatric Surgery</li>
            </ul>
            <a href="department-details.html" class="highlight-cta">Learn More</a>
          </div>
        </div>

        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
          <div class="department-highlight">
            <div class="highlight-icon">
              <i class="bi bi-activity"></i>
            </div>
            <h4>Cancer Treatment</h4>
            <p>Multidisciplinary oncology program offering personalized cancer care with latest therapeutic
              innovations.</p>
            <ul class="highlight-list">
              <li>Precision Medicine</li>
              <li>Immunotherapy</li>
              <li>Radiation Oncology</li>
            </ul>
            <a href="department-details.html" class="highlight-cta">Learn More</a>
          </div>
        </div>

      </div>

      <!-- Emergency Banner -->
      <div class="emergency-banner" data-aos="fade-up" data-aos-delay="400">
        <div class="row align-items-center">
          <div class="col-lg-8">
            <div class="emergency-content">
              <h3>Emergency Services Available 24/7</h3>
              <p>Our emergency department is equipped with state-of-the-art technology and staffed by board-certified
                emergency physicians ready to provide immediate care.</p>
            </div>
          </div>
          <div class="col-lg-4 text-lg-end">
            <a href="tel:+15551234567" class="emergency-btn">
              <i class="bi bi-telephone-fill"></i>
              Call Emergency: (555) 123-4567
            </a>
          </div>
        </div>
      </div>

    </div>

  </section>
</template>

<script setup>
// Use absolute paths since images are in public folder
const cardiologyImage = '/assets/img/health/cardiology-1.webp';
const neurologyImage = '/assets/img/health/neurology-4.webp';
</script>