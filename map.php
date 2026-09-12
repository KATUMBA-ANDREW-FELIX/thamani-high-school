<?php
/**
 * Thamani High School - Campus Map & Directions Page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Map & Directions - THAMANI HIGH SCHOOL - Kakiri</title>
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: {
                slate: '#1F2937', charcoal: '#111827', gold: '#D4AF37',
                darkGold: '#B8860B', lightGold: '#FEF3C7', lightGrey: '#F3F4F6',
                green: '#1F2937', darkGreen: '#111827', lightGreen: '#F3F4F6',
                maroon: '#800000', lightMaroon: '#FDF2F2'
            }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans flex flex-col min-h-screen">

    <!-- Top Announcement Bar -->
    <div class="bg-gray-950 text-white text-xs py-2.5 px-6 border-b border-brand-gold/30">
        <div class="max-w-7xl mx-auto w-full flex justify-between items-center">
            <span class="flex items-center gap-2">📍 THAMANI HIGH SCHOOL - Kakiri Main Campus, Wakiso District, Uganda</span>
            <span class="hidden sm:inline text-gray-300">📞 Enquiries: +256 414 123 456 | ✉️ info@thamani.ac.ug</span>
            <span class="bg-brand-gold text-gray-950 px-3 py-0.5 rounded-full font-extrabold uppercase tracking-wider text-[10px] shadow-sm">Visitor Guide</span>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200/80 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center">
                    <a href="home.php" class="-ml-3 flex-shrink-0 flex items-center gap-3 group">
                        <img class="h-12 w-auto transition-transform group-hover:scale-105" src="thamani-logo.png" alt="Thamani High School Logo" onerror="this.src='favicon.svg'">
                        <div class="flex flex-col">
                            <span class="text-2xl font-black tracking-tight text-gray-900">Thamani High School</span>
                            <span class="text-[11px] font-bold text-brand-gold tracking-widest uppercase flex items-center gap-1">
                                Kakiri Main Campus
                            </span>
                        </div>
                    </a>
                </div>
                <div class="hidden lg:flex items-center gap-3 ml-auto">
                    <a href="home.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Home</a>
                    <a href="enrollment.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Enrollment</a>
                    <a href="library.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Library</a>
                    <a href="gallery.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Gallery</a>
                    <a href="calendar.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Calendar & Fees</a>
                    <a href="alumni.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-gray-700 hover:bg-gray-900 hover:text-white">Alumni</a>
                    <a href="map.php" class="nav-link whitespace-nowrap px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition-all text-gray-900 bg-white border border-gray-300 shadow-sm hover:bg-gray-900 hover:text-white hover:border-gray-900">Campus Map</a>
                </div>
                <div class="hidden md:flex items-center gap-3 flex-shrink-0 ml-4">
                    <a href="teacher-login.php" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-gray-800 hover:bg-gray-900 transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                        <i data-lucide="log-in" class="w-3.5 h-3.5 text-amber-400"></i> Portal Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        <!-- Hero Header -->
        <section class="bg-gray-900 text-white py-14 border-b-4 border-brand-gold relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="max-w-3xl">
                    <span class="bg-brand-gold text-gray-950 px-3.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider inline-block mb-3">Kakiri, Wakiso District</span>
                    <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight">Campus Map & Visitor Guide</h1>
                    <p class="text-base text-gray-300 mt-3 leading-relaxed">Find your way around Thamani High School. Located along Kampala-Hoima Highway in Kakiri, Wakiso District.</p>
                </div>
            </div>
        </section>

        <!-- Map & Directions Section -->
        <section class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Map View Container -->
                <div class="lg:col-span-2 bg-white rounded-3xl p-4 shadow-md border border-gray-200 flex flex-col">
                    <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 mb-4">
                        <div class="flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-amber-600"></i>
                            <h2 class="text-lg font-bold text-gray-900">Interactive Location Map</h2>
                        </div>
                        <span class="text-xs bg-amber-100 text-amber-900 font-bold px-3 py-1 rounded-full">Kakiri Main Gate</span>
                    </div>
                    <div class="w-full h-[450px] rounded-2xl overflow-hidden shadow-inner border border-gray-200">
                        <iframe
                            title="Thamani High School Location"
                            class="w-full h-full border-0"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://maps.google.com/maps?q=Kakiri,Wakiso,Uganda&t=&z=14&ie=UTF8&iwloc=&output=embed">
                        </iframe>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4 mt-4 px-2 pt-2">
                        <div class="text-xs text-gray-500 font-medium">GPS Coordinates: <strong class="text-gray-800 font-mono">0.4815° N, 32.3892° E</strong></div>
                        <a href="https://maps.google.com/?q=Kakiri,Wakiso,Uganda" target="_blank" class="px-4 py-2 bg-gray-900 text-amber-400 font-bold rounded-xl text-xs flex items-center gap-2 shadow hover:bg-gray-800 transition-all">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Open in Google Maps
                        </a>
                    </div>
                </div>

                <!-- Getting Here Sidebar -->
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-3xl shadow-md border border-gray-200 space-y-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2 border-b border-gray-100 pb-3">
                            <i data-lucide="navigation" class="w-5 h-5 text-amber-600"></i> Driving Directions
                        </h3>
                        
                        <div class="space-y-4 text-sm text-gray-600">
                            <div>
                                <strong class="text-gray-900 block text-xs uppercase font-extrabold tracking-wider text-amber-700">From Kampala City Center:</strong>
                                <p class="mt-1">Take the Kampala-Hoima Highway towards Nansana, Wakiso, and Kakiri. The campus main entrance is located 2km after Kakiri Town Council on your left.</p>
                            </div>
                            
                            <hr class="border-gray-100">

                            <div>
                                <strong class="text-gray-900 block text-xs uppercase font-extrabold tracking-wider text-amber-700">Public Transport (Taxi / Matatu):</strong>
                                <p class="mt-1">Board a Hoima/Busunju matatu from the New Taxi Park or Nansana stage. Alight at Thamani High School Junction in Kakiri.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Campus Visitor Hours -->
                    <div class="bg-gray-900 text-white p-6 rounded-3xl shadow-md border-t-4 border-brand-gold space-y-3">
                        <h3 class="text-base font-bold text-amber-400 flex items-center gap-2">
                            <i data-lucide="clock" class="w-4 h-4"></i> Visiting & Office Hours
                        </h3>
                        <ul class="text-xs text-gray-300 space-y-2 font-medium">
                            <li class="flex justify-between border-b border-gray-800 pb-1.5"><span>Monday - Friday:</span> <strong class="text-white">8:00 AM - 5:00 PM</strong></li>
                            <li class="flex justify-between border-b border-gray-800 pb-1.5"><span>Saturday:</span> <strong class="text-white">9:00 AM - 1:00 PM</strong></li>
                            <li class="flex justify-between"><span>Sunday / Holidays:</span> <strong class="text-amber-400">Closed for Visitors</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Campus Facilities Grid -->
            <div class="mt-12">
                <h2 class="text-2xl font-black text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="building" class="w-6 h-6 text-amber-600"></i> Key Campus Facilities
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold mb-3">
                            <i data-lucide="landmark" class="w-5 h-5"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm">Administrative Block</h3>
                        <p class="text-xs text-gray-500 mt-1">Headteacher's Office, Bursar, Admissions Desk, and Registry.</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold mb-3">
                            <i data-lucide="flask-conical" class="w-5 h-5"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm">Science & Tech Labs</h3>
                        <p class="text-xs text-gray-500 mt-1">Modern Physics, Chemistry, Biology, and ICT Computer Laboratories.</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold mb-3">
                            <i data-lucide="trophy" class="w-5 h-5"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm">Sports Complex</h3>
                        <p class="text-xs text-gray-500 mt-1">Standard football pitch, netball courts, lawn tennis court, and track.</p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold mb-3">
                            <i data-lucide="home" class="w-5 h-5"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm">Boarding Hostels</h3>
                        <p class="text-xs text-gray-500 mt-1">Secure, self-contained residential dormitories for O-Level & A-Level students.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="bg-gray-900 text-white pt-12 pb-8 border-t-4 border-brand-gold mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-xl font-bold mb-3 flex items-center gap-2">
                        <img src="thamani-logo.png" class="h-8 w-auto" alt="Logo" onerror="this.src='favicon.svg'">
                        <span>Thamani High School</span>
                    </h3>
                    <p class="text-gray-300 text-xs leading-relaxed">Empowering the next generation of Ugandan leaders through excellence in education, culture, and character building.</p>
                </div>
                <div>
                    <h3 class="text-base font-bold mb-3 text-amber-400">Quick Links</h3>
                    <ul class="space-y-2 text-gray-300 text-xs font-medium">
                        <li><a href="home.php" class="hover:text-amber-400 transition-colors">Home</a></li>
                        <li><a href="enrollment.php" class="hover:text-amber-400 transition-colors">Enrollment Portal</a></li>
                        <li><a href="library.php" class="hover:text-amber-400 transition-colors">Digital Library</a></li>
                        <li><a href="calendar.php" class="hover:text-amber-400 transition-colors">Academic Calendar</a></li>
                        <li><a href="alumni.php" class="hover:text-amber-400 transition-colors">Alumni Directory</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-bold mb-3 text-amber-400">Contact Us</h3>
                    <ul class="space-y-2 text-gray-300 text-xs">
                        <li class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-amber-400"></i><span>Kakiri Main Campus, Wakiso District</span></li>
                        <li class="flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4 text-amber-400"></i><span>+256 414 123 456</span></li>
                        <li class="flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-amber-400"></i><span>info@thamani.ac.ug</span></li>
                    </ul>
                </div>
            </div>
            <div class="pt-6 border-t border-gray-800 text-center text-gray-400 text-xs flex justify-between items-center">
                <span>© 2026 Thamani High School. All rights reserved.</span>
                <span>Kakiri, Wakiso District</span>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
