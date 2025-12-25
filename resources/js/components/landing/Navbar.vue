<template>
  <header id="header" class="header fixed-top">

    <div class="topbar d-flex align-items-center">
      <div class="container d-flex justify-content-center justify-content-md-between">
        <div class="contact-info d-flex align-items-center">
          <i class="bi bi-envelope d-flex align-items-center">
            <a href="mailto:contact@example.com" class="nav-link">contact@example.com</a>
          </i>
          <i class="bi bi-phone d-flex align-items-center ms-4">
            <span class="nav-text">+1 5589 55488 55</span>
          </i>
        </div>
        <div class="social-links d-none d-md-flex align-items-center">
          <a href="#!" class="twitter nav-link"><i class="bi bi-twitter-x"></i></a>
          <a href="#!" class="facebook nav-link"><i class="bi bi-facebook"></i></a>
          <a href="#!" class="instagram nav-link"><i class="bi bi-instagram"></i></a>
          <a href="#!" class="linkedin nav-link"><i class="bi bi-linkedin"></i></a>
        </div>
      </div>
    </div><!-- End Top Bar -->

    <div class="branding d-flex align-items-center">

      <div class="container position-relative d-flex align-items-center justify-content-between">
        <a href="index.html" class="logo d-flex align-items-center nav-link">
          <!-- Uncomment the line below if you also wish to use an image logo -->
          <!-- <img src="assets/img/logo.webp" alt=""> -->
          <h1 class="sitename">Billing</h1>
        </a>

        <nav id="navmenu" class="navmenu">
          <ul>
            <li><a href="index.html" class="active nav-link">Home</a></li>
            <li><a href="about.html" class="nav-link">About</a></li>
            <li><a href="departments.html" class="nav-link">Departments</a></li>
            <li><a href="services.html" class="nav-link">Services</a></li>
            <li><a href="doctors.html" class="nav-link">Doctors</a></li>
            <li class="dropdown">
              <a href="#" class="nav-link"><span>More Pages</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
              <ul>
                <li><a href="department-details.html" class="nav-link">Department Details</a></li>
                <li><a href="service-details.html" class="nav-link">Service Details</a></li>
                <li><a href="appointment.html" class="nav-link">Appointment</a></li>
                <li><a href="testimonials.html" class="nav-link">Testimonials</a></li>
                <li><a href="faq.html" class="nav-link">Frequently Asked Questions</a></li>
                <li><a href="gallery.html" class="nav-link">Gallery</a></li>
                <li><a href="terms.html" class="nav-link">Terms</a></li>
                <li><a href="privacy.html" class="nav-link">Privacy</a></li>
                <li><a href="404.html" class="nav-link">404</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="#" class="nav-link"><span>Dropdown</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
              <ul>
                <li><a href="#" class="nav-link">Dropdown 1</a></li>
                <li class="dropdown">
                  <a href="#" class="nav-link"><span>Deep Dropdown</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                  <ul>
                    <li><a href="#" class="nav-link">Deep Dropdown 1</a></li>
                    <li><a href="#" class="nav-link">Deep Dropdown 2</a></li>
                    <li><a href="#" class="nav-link">Deep Dropdown 3</a></li>
                    <li><a href="#" class="nav-link">Deep Dropdown 4</a></li>
                    <li><a href="#" class="nav-link">Deep Dropdown 5</a></li>
                  </ul>
                </li>
                <li><a href="#" class="nav-link">Dropdown 2</a></li>
                <li><a href="#" class="nav-link">Dropdown 3</a></li>
                <li><a href="#" class="nav-link">Dropdown 4</a></li>
              </ul>
            </li>
            <li><a href="contact.html" class="nav-link">Contact</a></li>
          </ul>
          <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
        </nav>

      </div>

    </div>

  </header>
</template>

<script setup>
// No script needed for now
</script>

<style scoped>
/* Reset all text decorations within this component */
.header * {
  text-decoration: none !important;
}

/* Remove underlines from all links */
.header a,
.header .nav-link,
.header a:hover,
.header a:focus,
.header a:active {
  text-decoration: none !important;
}

/* Style for specific elements */
.header .topbar a,
.header .topbar .nav-link {
  color: inherit;
  transition: color 0.3s ease;
}

.header .topbar a:hover,
.header .topbar .nav-link:hover {
  color: #ffffff;
  opacity: 0.8;
}

.header .social-links a {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  transition: all 0.3s ease;
}

.header .social-links a:hover {
  background: #0d6efd;
  color: #ffffff;
  transform: translateY(-2px);
}

/* Navigation menu styles */
.header .navmenu ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  align-items: center;
}

.header .navmenu > ul > li {
  position: relative;
  margin: 0 0.5rem;
}

.header .navmenu a {
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
  color: #333;
  font-weight: 500;
  position: relative;
  transition: all 0.3s ease;
}

.header .navmenu a:hover {
  color: #0d6efd;
}

.header .navmenu .active {
  color: #0d6efd;
}

/* Dropdown styles */
.header .navmenu .dropdown ul {
  display: none;
  position: absolute;
  left: 0;
  top: 100%;
  min-width: 200px;
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  border-radius: 4px;
  z-index: 99;
  padding: 0.5rem 0;
}

.header .navmenu .dropdown:hover > ul {
  display: block;
}

.header .navmenu .dropdown ul li {
  min-width: 100%;
}

.header .navmenu .dropdown ul a {
  padding: 0.75rem 1.5rem;
  color: #555;
  white-space: nowrap;
}

.header .navmenu .dropdown ul a:hover {
  background: #f8f9fa;
  color: #0d6efd;
}

/* Logo/Sitename */
.header .sitename {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  color: #0d6efd;
  text-decoration: none !important;
}

/* Contact info */
.header .contact-info i {
  margin-right: 0.5rem;
  color: rgba(255, 255, 255, 0.8);
}

.header .contact-info a,
.header .contact-info span {
  color: rgba(255, 255, 255, 0.9);
}

.header .contact-info a:hover {
  color: #ffffff;
  text-decoration: none !important;
}

/* Top bar background */
.header .dark-background {
  background: #333;
  color: rgba(255, 255, 255, 0.9);
  padding: 0.5rem 0;
}

/* Mobile nav toggle */
.header .mobile-nav-toggle {
  display: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #333;
}

/* Responsive adjustments */
@media (max-width: 1199px) {
  .header .navmenu {
    position: fixed;
    top: 0;
    right: -100%;
    width: 300px;
    height: 100vh;
    background: #ffffff;
    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
    transition: 0.3s;
    z-index: 9999;
    padding: 2rem;
    overflow-y: auto;
  }
  
  .header .navmenu.mobile-nav-active {
    right: 0;
  }
  
  .header .navmenu ul {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .header .navmenu > ul > li {
    margin: 0.5rem 0;
    width: 100%;
  }
  
  .header .mobile-nav-toggle {
    display: block;
  }
}
</style>