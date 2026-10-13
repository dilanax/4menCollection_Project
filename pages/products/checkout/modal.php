<!-- Success Confirmation Modal -->
<div id="success-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full text-center transform scale-95 transition-transform duration-300 shadow-2xl border border-slate-100">
        <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-5 border border-emerald-100">
            <svg class="w-8 h-8 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h2 class="text-2xl font-black text-slate-900 mb-2">Order Confirmed!</h2>
        <p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed">
            Thank you for purchasing with ReadersHub. Your order invoice is <strong id="modal-order-number" class="text-sky-700 font-mono"></strong>. Our team will contact you for dispatch confirmation.
        </p>
        <p class="text-xs font-semibold text-sky-700 mb-6 hidden bg-sky-50 p-3 rounded-xl border border-sky-100" id="account-creation-msg">
            An account has been created for you automatically! Please check your email for temporary login credentials.
        </p>
        <a href="/" class="block w-full bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs uppercase tracking-wider py-3.5 px-4 rounded-xl transition shadow-md shadow-sky-700/20">
            Return to Home &rarr;
        </a>
    </div>
</div>