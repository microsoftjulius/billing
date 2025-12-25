<template>
  <section id="featured-services" class="featured-services section">

    <!-- Section Title -->
    <div class="container section-title" data-aos="fade-up">
      <h2>Featured Services</h2>
      <p>Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit</p>
    </div><!-- End Section Title -->

    <div class="container" data-aos="fade-up" data-aos-delay="100">

      <div class="row g-0">

        <!-- Main Featured Service -->
        <div class="col-lg-8" data-aos="fade-right" data-aos-delay="200">
          <div class="featured-service-main">
            <div class="service-image-wrapper">
              <img :src="consultationImage" alt="Premier Healthcare Services" class="img-fluid" loading="lazy">
              <div class="service-overlay">
                <div class="service-badge">
                  <i class="bi bi-heart-pulse"></i>
                  <span>Emergency Care</span>
                </div>
              </div>
            </div>
            <div class="service-details">
              <h2>Comprehensive Healthcare Excellence</h2>
              <p>Mauris blandit aliquet elit, eget tincidunt nibh pulvinar a. Vestibulum ante ipsum primis in faucibus
                orci luctus et ultrices posuere cubilia curae donec velit neque.</p>
              <a href="#!" class="main-cta">Explore Our Services</a>
            </div>
          </div>
        </div>

        <!-- Sidebar Services -->
        <div class="col-lg-4" data-aos="fade-left" data-aos-delay="300">
          <div class="services-sidebar">

            <div class="service-item" data-aos="fade-up" data-aos-delay="400">
              <div class="service-icon-wrapper">
                <i class="bi bi-capsule"></i>
              </div>
              <div class="service-info">
                <h4>Dermatology Clinic</h4>
                <p>Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.</p>
                <a href="#!" class="service-link">Learn More</a>
              </div>
            </div>

            <div class="service-item" data-aos="fade-up" data-aos-delay="500">
              <div class="service-icon-wrapper">
                <i class="bi bi-bandaid"></i>
              </div>
              <div class="service-info">
                <h4>Surgery Center</h4>
                <p>Donec rutrum congue leo eget malesuada curabitur arcu erat accumsan id imperdiet et porttitor at
                  sem.</p>
                <a href="#!" class="service-link">Learn More</a>
              </div>
            </div>

            <div class="service-item" data-aos="fade-up" data-aos-delay="600">
              <div class="service-icon-wrapper">
                <i class="bi bi-activity"></i>
              </div>
              <div class="service-info">
                <h4>Diagnostics Lab</h4>
                <p>Vestibulum ac diam sit amet quam vehicula elementum sed sit amet dui cras ultricies ligula sed
                  magna.</p>
                <a href="#!" class="service-link">Learn More</a>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- Specialties Grid -->
      <div class="specialties-grid" data-aos="fade-up" data-aos-delay="300">
        <div class="row align-items-center">

          <div class="col-lg-3 col-md-6">
            <div class="specialty-card">
              <div class="specialty-image">
                <img :src="maternalImage" alt="Maternal Care" class="img-fluid" loading="lazy">
              </div>
              <div class="specialty-content">
                <h5>Maternal Care</h5>
                <span>Expert pregnancy &amp; delivery support</span>
              </div>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="specialty-card">
              <div class="specialty-image">
                <img :src="vaccinationImage" alt="Vaccination" class="img-fluid" loading="lazy">
              </div>
              <div class="specialty-content">
                <h5>Vaccination</h5>
                <span>Complete immunization programs</span>
              </div>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="specialty-card">
              <div class="specialty-image">
                <img :src="emergencyImage" alt="Emergency Care" class="img-fluid" loading="lazy">
              </div>
              <div class="specialty-content">
                <h5>Emergency Care</h5>
                <span>24/7 critical care services</span>
              </div>
            </div>
          </div>

          <div class="col-lg-3 col-md-6">
            <div class="specialty-card">
              <div class="specialty-image">
                <img :src="facilitiesImage" alt="Advanced Tech" class="img-fluid" loading="lazy">
              </div>
              <div class="specialty-content">
                <h5>Advanced Technology</h5>
                <span>State-of-the-art medical equipment</span>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>

  </section>
</template>

<script setup>
// Use absolute paths since images are in public folder
const consultationImage = '/assets/img/health/consultation-4.webp';
const maternalImage = '/assets/img/health/maternal-2.webp';
const vaccinationImage = '/assets/img/health/vaccination-3.webp';
const emergencyImage = '/assets/img/health/emergency-1.webp';
const facilitiesImage = '/assets/img/health/facilities-6.webp';
</script>