<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>THAMANI ACADEMY - Kakiri | Excellence in Every Endeavor</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                green: '#1A472A', maroon: '#800000', gold: '#D4AF37',
                lightGreen: '#E8F5E9', darkGreen: '#0F2D1A', lightMaroon: '#FDF2F2'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .tab-btn.active { background-color: #1A472A; color: #ffffff; }
        .nav-link-active { background-color: #1A472A; color: #ffffff !important; }
        #page-loader {
            position: fixed; inset: 0; background: #1A472A;
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity .5s ease, visibility .5s ease;
        }
        #page-loader.hidden { opacity: 0; visibility: hidden; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <!-- Page Loader -->
    <div id="page-loader">
        <div class="text-center">
            <img src="thamani-logo.png" alt="Thamani Academy"
                 class="h-20 w-auto mx-auto mb-4 animate-pulse" onerror="this.style.display='none'">
            <div class="w-12 h-12 border-4 border-brand-gold border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>
    </div>

    <!-- Top Announcement Bar -->
    <div class="bg-brand-maroon text-white text-xs py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span>📍 THAMANI ACADEMY - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline">📞 Enquiries: +256 414 123 456 | ✉️ info@thamaniacademy.ac.ug</span>
            <span class="bg-brand-gold text-brand-green px-2.5 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Term III 2026 Active</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="home.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-12 w-auto" src="thamani-logo.png" alt="Thamani Academy Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold tracking-tight text-brand-green">Thamani Academy</span>
                            <span class="text-[10px] font-semibold text-brand-maroon tracking-widest uppercase">Kakiri - Uganda</span>
                        </div>
                    </a>
                </div>
                <div class="hidden lg:flex items-center space-x-2">
                    <a href="#" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-white bg-brand-green">Home</a>
                    <a href="enrollment.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Enrollment</a>
                    <a href="library.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Library</a>
                    <a href="gallery.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Gallery</a>
                    <a href="calendar.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Calendar & Fees</a>
                    <a href="alumni.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-brand-green">Alumni</a>
                </div>
                <div class="hidden md:flex items-center space-x-3">
                    <a href="teacher-login.php" class="px-4 py-2 rounded-md text-sm font-bold text-white bg-brand-maroon transition-transform hover:scale-105 shadow flex items-center gap-1.5">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> Teacher Portal
                    </a>
                    <a href="teacher-login.php" class="px-4 py-2 rounded-md text-sm font-bold text-brand-green bg-brand-gold transition-transform hover:scale-105 shadow flex items-center gap-1.5">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Admin Panel
                    </a>
                </div>
                <div class="lg:hidden flex items-center">
                    <button onclick="toggleMobileMenu();" class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-brand-green hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-200 px-4 pt-2 pb-4 space-y-2">
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Enrollment</a>
            <a href="library.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Digital Library</a>
            <a href="gallery.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Gallery</a>
            <a href="calendar.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Calendar & Fees</a>
            <a href="alumni.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-800 hover:bg-brand-lightGreen">Alumni</a>
            <div class="pt-2 border-t border-gray-100 flex flex-col gap-2">
                <a href="teacher-login.php" class="w-full py-2.5 rounded-md font-bold text-white bg-brand-maroon text-center">Teacher Portal</a>
                <a href="teacher-login.php" class="w-full py-2.5 rounded-md font-bold text-brand-green bg-brand-gold text-center">Admin Panel</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <section id="page-home">
            <!-- Hero Banner -->
            <div class="relative h-[600px] flex items-center justify-center text-white overflow-hidden bg-brand-darkGreen">
                <div class="absolute inset-0 z-0 bg-cover bg-center transition-transform duration-1000 hover:scale-105 opacity-50" style="background-image: url('school.JPG');"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-brand-darkGreen via-brand-green/70 to-transparent"></div>
                <div class="relative z-10 max-w-4xl mx-auto text-center px-4">
                    <span class="inline-block px-4 py-1.5 rounded-full bg-brand-gold text-brand-green font-bold text-sm mb-4 uppercase tracking-wider shadow-lg">Thamani Academy - Kakiri</span>
                    <h1 class="text-4xl md:text-6xl font-extrabold mb-6 tracking-tight leading-tight">Excellence in Every Endeavor</h1>
                    <p class="text-lg md:text-2xl mb-8 text-gray-200 max-w-3xl mx-auto font-light">Welcome to Thamani Academy, where we nurture the leaders of tomorrow with Ugandan values and global standards.</p>
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="enrollment.php" class="px-8 py-3.5 rounded-full font-bold text-lg bg-brand-gold text-brand-green transition-all hover:shadow-xl hover:-translate-y-1 hover:bg-yellow-400">Enroll Now</a>
                        <a href="gallery.php" class="px-8 py-3.5 rounded-full font-bold text-lg border-2 border-white hover:bg-white hover:text-brand-green transition-all">Explore Campus</a>
                    </div>
                </div>
            </div>

            <!-- 4 Quick Access Feature Cards -->
            <div class="py-20 bg-white border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <a href="library.php" class="block p-8 rounded-2xl bg-gray-50 text-center shadow-sm hover:shadow-xl transition-all group border border-gray-100 hover:border-brand-green/30">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-6 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white transition-colors"><i data-lucide="book-open" class="w-8 h-8"></i></div>
                            <h3 class="text-xl font-bold mb-3 text-brand-green">Digital Library</h3>
                            <p class="text-gray-600 mb-6 text-sm">Access local curriculum materials anytime.</p>
                            <span class="inline-flex items-center font-bold text-brand-maroon text-sm group-hover:translate-x-1 transition-transform">Learn More <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i></span>
                        </a>
                        <a href="enrollment.php" class="block p-8 rounded-2xl bg-gray-50 text-center shadow-sm hover:shadow-xl transition-all group border border-gray-100 hover:border-brand-green/30">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-6 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white transition-colors"><i data-lucide="users" class="w-8 h-8"></i></div>
                            <h3 class="text-xl font-bold mb-3 text-brand-green">Enrollment Portal</h3>
                            <p class="text-gray-600 mb-6 text-sm">Searchable student records and registration.</p>
                            <span class="inline-flex items-center font-bold text-brand-maroon text-sm group-hover:translate-x-1 transition-transform">Learn More <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i></span>
                        </a>
                        <a href="gallery.php" class="block p-8 rounded-2xl bg-gray-50 text-center shadow-sm hover:shadow-xl transition-all group border border-gray-100 hover:border-brand-green/30">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-6 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white transition-colors"><i data-lucide="trophy" class="w-8 h-8"></i></div>
                            <h3 class="text-xl font-bold mb-3 text-brand-green">Sports & Culture</h3>
                            <p class="text-gray-600 mb-6 text-sm">Vibrant gallery of our achievements.</p>
                            <span class="inline-flex items-center font-bold text-brand-maroon text-sm group-hover:translate-x-1 transition-transform">Learn More <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i></span>
                        </a>
                        <a href="calendar.php" class="block p-8 rounded-2xl bg-gray-50 text-center shadow-sm hover:shadow-xl transition-all group border border-gray-100 hover:border-brand-green/30">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-6 bg-brand-green/10 text-brand-green group-hover:bg-brand-green group-hover:text-white transition-colors"><i data-lucide="calendar" class="w-8 h-8"></i></div>
                            <h3 class="text-xl font-bold mb-3 text-brand-green">Academic Calendar</h3>
                            <p class="text-gray-600 mb-6 text-sm">Stay updated with termly schedules.</p>
                            <span class="inline-flex items-center font-bold text-brand-maroon text-sm group-hover:translate-x-1 transition-transform">Learn More <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Latest News Section -->
            <div class="py-20 bg-gray-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-end mb-12">
                        <div>
                            <h2 class="text-4xl font-bold text-brand-green mb-2">Latest News</h2>
                            <p class="text-gray-600">Stay updated with the latest happenings at Thamani Academy.</p>
                        </div>
                        <a href="admin.php" class="px-6 py-2.5 rounded-lg font-bold border-2 border-brand-green text-brand-green hover:bg-brand-green hover:text-white transition-colors text-sm">View All Circulars</a>
                    </div>
                    <div id="home-circulars-container" class="grid grid-cols-1 md:grid-cols-3 gap-8"></div>
                </div>
            </div>

            <!-- Alumni CTA Section -->
            <div class="py-20 bg-brand-maroon text-white text-center">
                <div class="max-w-4xl mx-auto px-4">
                    <h2 class="text-4xl font-bold mb-6">Join Our Alumni Network</h2>
                    <p class="text-xl mb-8 opacity-90 font-light">Are you a former student? Register now for upcoming networking events and stay connected with your alma mater.</p>
                    <a href="alumni.php" class="inline-block px-10 py-4 rounded-full font-bold text-lg text-brand-maroon bg-white hover:bg-brand-gold transition-all hover:scale-105 shadow-xl">Register for Alumni Events</a>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="bg-brand-green text-white pt-16 pb-10 mt-auto border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <h3 class="text-2xl font-bold mb-4 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-10 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani Academy</span>
                    </h3>
                    <p class="text-gray-300 text-sm leading-relaxed mb-6">Empowering the next generation of Ugandan leaders through excellence in education, culture, and character building.</p>
                    <div class="flex space-x-5 text-brand-gold">
                        <i data-lucide="facebook" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="twitter" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                        <i data-lucide="instagram" class="w-6 h-6 cursor-pointer hover:text-white transition-colors"></i>
                    </div>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-3 text-gray-300 text-sm">
                        <li><a href="enrollment.php" class="hover:text-brand-gold transition-colors">Student Enrollment</a></li>
                        <li><a href="library.php" class="hover:text-brand-gold transition-colors">Digital Library</a></li>
                        <li><a href="calendar.php" class="hover:text-brand-gold transition-colors">Academic Calendar</a></li>
                        <li><a href="alumni.php" class="hover:text-brand-gold transition-colors">Alumni Network</a></li>
                        <li><a href="teacher-login.php" class="hover:text-brand-gold transition-colors">Teacher Portal</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-2xl font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-4 text-gray-300 text-sm">
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="w-5 h-5 text-brand-gold"></i><span>Plot 45, Education Road, Kampala, Uganda</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="phone" class="w-5 h-5 text-brand-gold"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-3"><i data-lucide="mail" class="w-5 h-5 text-brand-gold"></i><span>info@thamaniacademy.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-brand-darkGreen text-center text-gray-400 text-sm flex justify-between items-center">
                <span>© 2026 Thamani Academy. All rights reserved.</span>
                <span>Standalone Web App</span>
            </div>
        </div>
    </footer>

    <script src="app.js"></script>
    <script>
        lucide.createIcons();
        window.addEventListener('load', () => {
            setTimeout(() => document.getElementById('page-loader')?.classList.add('hidden'), 400);
        });
    </script>
</body>
</html>