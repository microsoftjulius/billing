// resources/js/app.js
import { createApp } from 'vue';
import LandingPage from './pages/LandingPage.vue';

// Import Bootstrap
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

// Import other vendors
import 'aos/dist/aos.css';
import AOS from 'aos';
import GLightbox from 'glightbox';
import Swiper from 'swiper/bundle';

// Import your main CSS
import '../css/app.css';

// Initialize AOS
AOS.init({
    duration: 1000,
    once: true
});

// Initialize GLightbox
const lightbox = GLightbox({
    selector: '.glightbox'
});

// Initialize Swiper if needed
// new Swiper('.swiper-container', {...});

// Create Vue app
createApp(LandingPage).mount('#app');