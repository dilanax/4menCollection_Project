<?php
// Set response code to 404 Not Found
http_response_code(404);
?>

<div class="min-h-[70vh] flex items-center justify-center px-4 py-16 sm:py-24">
    <div class="max-w-xl w-full text-center">
        
        <!-- Graphic / Illustration -->
        <div class="relative flex items-center justify-center mb-6">
            <span class="text-[120px] sm:text-[160px] font-black text-sky-100 select-none leading-none tracking-tighter">
                404
            </span>
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-white shadow-xl shadow-sky-100 border border-sky-100 flex items-center justify-center text-sky-500 transform -rotate-6 transition-transform hover:rotate-0 duration-300">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Text Messaging -->
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
            Page or Book Not Found
        </h1>
        <p class="text-sm sm:text-base text-slate-500 max-w-md mx-auto leading-relaxed mb-8">
            The book or page you are looking for might have been moved, renamed, or is temporarily out of shelf.
        </p>

        <!-- Quick Book Search Field -->
        <form action="/shop" method="GET" class="max-w-md mx-auto mb-8">
            <div class="flex items-center rounded-full border-2 border-sky-300 focus-within:border-sky-500 bg-white p-1 shadow-sm transition duration-200">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search for books, authors, or genres..." 
                    class="w-full bg-transparent text-sm text-slate-700 pl-4 pr-2 py-1.5 outline-none"
                    required
                >
                <button type="submit" class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-full text-xs font-bold transition flex items-center gap-1.5 shrink-0">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Search
                </button>
            </div>
        </form>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-full shadow-sm transition duration-200">
                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Back to Home
            </a>
            <a href="/shop" class="inline-flex items-center gap-2 bg-sky-50 hover:bg-sky-100 text-sky-600 font-bold text-xs sm:text-sm px-6 py-3 rounded-full transition duration-200 border border-sky-200">
                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                Browse Shop
            </a>
        </div>

    </div>
</div>