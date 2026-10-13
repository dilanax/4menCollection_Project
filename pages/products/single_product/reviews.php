<!-- Book Tabs (Overview & Reviews) -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200/90 p-6 sm:p-10 mb-12" id="reviews-section">
    
    <div class="flex border-b border-slate-200 mb-8 gap-6 sm:gap-10">
        <button class="pb-3 text-sm sm:text-base font-bold text-sky-700 border-b-2 border-sky-700 transition" id="tab-btn-desc" onclick="switchTab('desc')">Book Overview</button>
        <button class="pb-3 text-sm sm:text-base font-bold text-slate-400 hover:text-slate-700 transition" id="tab-btn-reviews" onclick="switchTab('reviews')">Reader Reviews (<?= $total_reviews; ?>)</button>
    </div>
    
    <!-- Tab: Description -->
    <div id="tab-desc" class="text-slate-600 leading-relaxed text-xs sm:text-sm space-y-4">
        <?= nl2br(htmlspecialchars($product['description'] ?: 'No extended description available for this book title.', ENT_QUOTES, 'UTF-8')); ?>
        
        <?php if (!empty($product['video_url'])): ?>
            <div class="pt-4">
                <a href="<?= htmlspecialchars($product['video_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-700 text-xs font-bold rounded-xl hover:bg-rose-100 transition border border-rose-200">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/></svg>
                    Watch Book Preview / Video
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab: Reviews -->
    <div id="tab-reviews" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
            
            <!-- Write Review Column -->
            <div class="lg:col-span-5">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-4">Write a Review</h3>
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 text-center">
                        <p class="text-xs sm:text-sm text-slate-600 mb-4">Sign in to leave a review and rating for this book.</p>
                        <a href="/login" class="inline-block bg-sky-700 hover:bg-sky-800 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition shadow-sm">Login to Review</a>
                    </div>
                <?php elseif ($has_reviewed): ?>
                    <div class="bg-emerald-50 text-emerald-800 p-4 rounded-2xl border border-emerald-200 text-xs sm:text-sm font-semibold">
                        Thank you! You have already submitted your review for this title.
                    </div>
                <?php elseif (!$can_review): ?>
                    <div class="bg-amber-50 text-amber-800 p-4 rounded-2xl border border-amber-200 text-xs sm:text-sm">
                        <strong>Verified Purchasers Only:</strong> You can only rate and review books you have ordered through ReadersHub once completed.
                    </div>
                <?php else: ?>
                    <form id="review-form" class="space-y-4">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="product_id" value="<?= $product_id; ?>">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Rating (1 to 5 Stars)</label>
                            <div class="flex flex-row-reverse justify-end items-center text-slate-300 gap-1 star-rating-wrap">
                                <input type="radio" id="star5" name="rating" value="5" class="peer hidden" required />
                                <label for="star5" class="cursor-pointer hover:text-amber-400 peer-checked:text-amber-400">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </label>
                                <input type="radio" id="star4" name="rating" value="4" class="peer hidden" />
                                <label for="star4" class="cursor-pointer hover:text-amber-400 peer-checked:text-amber-400">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </label>
                                <input type="radio" id="star3" name="rating" value="3" class="peer hidden" />
                                <label for="star3" class="cursor-pointer hover:text-amber-400 peer-checked:text-amber-400">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </label>
                                <input type="radio" id="star2" name="rating" value="2" class="peer hidden" />
                                <label for="star2" class="cursor-pointer hover:text-amber-400 peer-checked:text-amber-400">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </label>
                                <input type="radio" id="star1" name="rating" value="1" class="peer hidden" />
                                <label for="star1" class="cursor-pointer hover:text-amber-400 peer-checked:text-amber-400">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Review Commentary *</label>
                            <textarea name="comment" rows="4" required class="w-full border border-slate-200 rounded-xl p-3 text-xs sm:text-sm text-slate-800 focus:ring-2 focus:ring-sky-100 focus:border-sky-700 outline-none transition" placeholder="Share your reading experience with others..."></textarea>
                        </div>
                        
                        <div id="review-msg" class="hidden text-xs font-bold"></div>

                        <button type="submit" id="submit-review-btn" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-sm">Submit Review</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Existing Reviews List -->
            <div class="lg:col-span-7">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 mb-4">Reader Feedback</h3>
                <?php if (empty($reviews)): ?>
                    <p class="text-xs sm:text-sm text-slate-400 italic">No reviews yet for this title. Be the first to share your thoughts!</p>
                <?php else: ?>
                    <div class="space-y-3.5 max-h-[420px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($reviews as $r): ?>
                            <div class="bg-slate-50/80 p-4 rounded-2xl border border-slate-200/80">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-sky-100 text-sky-800 font-bold text-xs flex items-center justify-center">
                                            <?= strtoupper(substr($r['user_name'], 0, 1)); ?>
                                        </div>
                                        <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($r['user_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-medium"><?= date('M d, Y', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div class="flex text-amber-400 text-xs mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $r['rating']): ?>
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        <?php else: ?>
                                            <svg class="w-3.5 h-3.5 text-slate-200 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>