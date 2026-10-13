<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// Fetch Dropdowns
$countries = $pdo->query("SELECT * FROM geo_countries WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$provinces = $pdo->query("SELECT * FROM geo_regions WHERE type = 'province' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query("SELECT * FROM geo_regions WHERE type = 'district' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Delivery Rates
$sql = "SELECT r.*, c.name as country_name, p.name as province_name, d.name as district_name 
        FROM geo_delivery_rates r 
        LEFT JOIN geo_countries c ON r.country_id = c.id
        LEFT JOIN geo_regions p ON r.province_id = p.id 
        LEFT JOIN geo_regions d ON r.district_id = d.id 
        ORDER BY r.country_id ASC, r.province_id ASC, r.district_id ASC, r.cost ASC";
$rates = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch Weight Rates
$weight_rates = [];
try {
    $weight_rates = $pdo->query("SELECT * FROM geo_weight_rates ORDER BY min_weight ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Rates & Rules | ReadersHub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, input, select, button { font-family: 'Poppins', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc] text-slate-800 antialiased">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50/50">
        
        <!-- Header -->
        <header class="bg-white/95 backdrop-blur-md shadow-xs border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-3.5 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-open-header" type="button" class="md:hidden p-2 text-slate-600 hover:text-sky-700 focus:outline-none rounded-xl hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Logistics Settings</span>
                    <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight">Delivery Rates & Weight Rules</h1>
                </div>
            </div>

            <!-- Action Trigger Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" onclick="openRateModal('add')" class="bg-sky-700 hover:bg-sky-800 text-white px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md shadow-sky-700/20 flex items-center gap-1.5 active:scale-98">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Delivery Rate</span>
                </button>
                <button type="button" onclick="openWeightModal('add')" class="bg-slate-900 hover:bg-black text-white px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-xs flex items-center gap-1.5 active:scale-98">
                    <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Weight Range</span>
                </button>
                <button type="button" onclick="openRegionModal()" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                    <span>+ Location Region</span>
                </button>
                <a href="shipping_classes.php" class="bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 px-3.5 py-2 rounded-xl text-xs font-bold transition">
                    Parcel Classes
                </a>
            </div>
        </header>

        <!-- Main Full Width Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 xl:p-10 space-y-8">
            
            <!-- SECTION 1: LOCATION RATES TABLE -->
            <div class="w-full bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Regional Logistics</span>
                        <h3 class="text-base font-black text-slate-900 tracking-tight">Location-based Delivery Rules</h3>
                    </div>
                    <button type="button" onclick="openRateModal('add')" class="self-start sm:self-auto text-xs font-bold bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 px-3.5 py-1.5 rounded-xl transition">
                        + New Location Rate
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                                <th class="px-5 py-3.5">Coverage Scope</th>
                                <th class="px-5 py-3.5">Method Title</th>
                                <th class="px-5 py-3.5 text-right">Delivery Cost</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($rates)): ?>
                                <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">No delivery rules defined. Click "Add Delivery Rate" above.</td></tr>
                            <?php else: ?>
                                <?php foreach($rates as $r): ?>
                                <tr class="hover:bg-slate-50/60 transition group">
                                    <td class="px-5 py-3.5">
                                        <?php if(!empty($r['district_name'])): ?>
                                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($r['district_name']); ?> District</span>
                                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($r['province_name']); ?> (<?= htmlspecialchars($r['country_name']); ?>)</span>
                                        <?php elseif(!empty($r['province_name'])): ?>
                                            <span class="font-bold text-sky-700 block"><?= htmlspecialchars($r['province_name']); ?></span>
                                            <span class="text-[10px] text-slate-400">All districts in province</span>
                                        <?php else: ?>
                                            <span class="font-extrabold text-emerald-700 block uppercase tracking-wider text-[11px]"><?= htmlspecialchars($r['country_name']); ?> (Islandwide)</span>
                                            <span class="text-[10px] text-slate-400">Default fallback for all regions</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-bold text-slate-800 block"><?= htmlspecialchars($r['method_title']); ?></span>
                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($r['estimated_days']); ?></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <?php if($r['shipping_type'] === 'free'): ?>
                                            <span class="font-black text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded text-[10px] uppercase">Free Delivery</span>
                                        <?php else: ?>
                                            <span class="font-black text-slate-900 text-sm">Rs. <?= number_format($r['cost'], 2); ?></span>
                                            <?php if(!empty($r['min_order_free'])): ?>
                                                <span class="block text-[9px] font-bold text-emerald-600">Free if order &gt; Rs. <?= number_format($r['min_order_free'], 0); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button type="button" onclick='openRateModal("edit", <?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="p-1.5 text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition" title="Edit Rate Rule">
                                                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <form action="shipping_zones_action.php" method="POST" onsubmit="return confirm('Delete this rate rule?');" class="inline">
                                                <input type="hidden" name="action" value="delete_rate_rule">
                                                <input type="hidden" name="rate_id" value="<?= $r['id']; ?>">
                                                <button type="submit" class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition" title="Delete">
                                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 2: WEIGHT RANGES TABLE -->
            <div class="w-full bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-sky-700 block">Package Slabs</span>
                        <h3 class="text-base font-black text-slate-900 tracking-tight">Weight-based Incremental Pricing</h3>
                    </div>
                    <button type="button" onclick="openWeightModal('add')" class="self-start sm:self-auto text-xs font-bold bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-200 px-3.5 py-1.5 rounded-xl transition">
                        + New Weight Slab
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] uppercase tracking-widest font-extrabold">
                                <th class="px-5 py-3.5">Weight Slab Label</th>
                                <th class="px-5 py-3.5">Weight Range</th>
                                <th class="px-5 py-3.5 text-right">Additional Cost</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($weight_rates)): ?>
                                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">No weight slabs defined.</td></tr>
                            <?php else: ?>
                                <?php foreach($weight_rates as $wr): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-5 py-3.5 font-bold text-slate-800">
                                        <?= htmlspecialchars($wr['label'] ?? 'Custom Range'); ?>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-slate-600">
                                        <?= number_format($wr['min_weight'], 2); ?> kg &mdash; <?= !empty($wr['max_weight']) ? number_format($wr['max_weight'], 2) . ' kg' : '&infin;'; ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-black text-sky-700 text-sm">
                                        + Rs. <?= number_format($wr['extra_cost'], 2); ?>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button type="button" onclick='openWeightModal("edit", <?= json_encode($wr, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="p-1.5 text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition" title="Edit Weight Slab">
                                                <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <form action="shipping_zones_action.php" method="POST" onsubmit="return confirm('Delete this weight range?');" class="inline">
                                                <input type="hidden" name="action" value="delete_weight_rate">
                                                <input type="hidden" name="weight_id" value="<?= $wr['id']; ?>">
                                                <button type="submit" class="p-1.5 text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition" title="Delete">
                                                    <svg class="w-4 h-4 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- ========================================== -->
    <!-- MODAL 1: ADD / EDIT LOCATION RATE RULE     -->
    <!-- ========================================== -->
    <div id="rateModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden p-6 border border-slate-100">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <h3 id="rateModalTitle" class="font-extrabold text-slate-900 text-sm">Add Location Rate Rule</h3>
                <button type="button" onclick="closeModal('rateModal')" class="text-slate-400 hover:text-rose-600 text-xl font-bold">&times;</button>
            </div>

            <form action="shipping_zones_action.php" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="save_rate_rule">
                <input type="hidden" name="rate_id" id="modal_rate_id" value="">

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Method Title *</label>
                    <input type="text" name="method_title" id="modal_method_title" required value="Standard Delivery" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-semibold outline-none focus:border-sky-700">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Country *</label>
                        <select name="country_id" id="modal_country_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 outline-none focus:border-sky-700 cursor-pointer">
                            <?php foreach($countries as $c): ?>
                                <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Province (පළාත)</label>
                        <select name="province_id" id="modal_province_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 outline-none focus:border-sky-700 cursor-pointer">
                            <option value="">Country-wide (All Provinces)</option>
                            <?php foreach($provinces as $prov): ?>
                                <option value="<?= $prov['id']; ?>"><?= htmlspecialchars($prov['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">District (දිස්ත්‍රික්කය)</label>
                    <select name="district_id" id="modal_district_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 outline-none focus:border-sky-700 cursor-pointer">
                        <option value="">Entire Province (All Districts)</option>
                        <?php foreach($districts as $dist): ?>
                            <option value="<?= $dist['id']; ?>" data-parent="<?= $dist['parent_id']; ?>"><?= htmlspecialchars($dist['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pricing Mode Toggle -->
                <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2.5">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-800 bg-white p-2 rounded-xl border border-slate-200 cursor-pointer">
                            <input type="radio" name="shipping_type" id="modal_type_paid" value="paid" checked onchange="toggleModalPriceFields(false)" class="text-sky-700"> Fixed Fee
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-800 bg-white p-2 rounded-xl border border-slate-200 cursor-pointer">
                            <input type="radio" name="shipping_type" id="modal_type_free" value="free" onchange="toggleModalPriceFields(true)" class="text-sky-700"> Free Delivery
                        </label>
                    </div>

                    <div id="modal_price_group" class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Fee (Rs.)</span>
                            <input type="number" step="0.01" name="cost" id="modal_cost" value="350.00" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-black text-slate-900">
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Free if Order &gt; (Rs.)</span>
                            <input type="number" step="0.01" name="min_order_free" id="modal_min_order_free" placeholder="Optional" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Estimated Days</label>
                    <input type="text" name="estimated_days" id="modal_estimated_days" value="2-3 Business Days" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 outline-none focus:border-sky-700">
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('rateModal')" class="px-4 py-2 rounded-xl text-slate-600 font-bold hover:bg-slate-100">Cancel</button>
                    <button type="submit" id="modalRateSubmitBtn" class="bg-sky-700 hover:bg-sky-800 text-white font-bold px-5 py-2 rounded-xl uppercase tracking-wider shadow-sm">Save Rule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: ADD / EDIT WEIGHT RANGE SLAB      -->
    <!-- ========================================== -->
    <div id="weightModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden p-6 border border-slate-100">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <h3 id="weightModalTitle" class="font-extrabold text-slate-900 text-sm">Add Weight Range Slab</h3>
                <button type="button" onclick="closeModal('weightModal')" class="text-slate-400 hover:text-rose-600 text-xl font-bold">&times;</button>
            </div>

            <form action="shipping_zones_action.php" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="save_weight_rate">
                <input type="hidden" name="weight_id" id="modal_weight_id" value="">

                <div>
                    <label class="block font-bold text-slate-600 mb-1">Range Label</label>
                    <input type="text" name="label" id="modal_weight_label" required placeholder="e.g. 1kg to 2kg Parcel" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 outline-none focus:border-sky-700">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Min Weight (kg) *</label>
                        <input type="number" step="0.01" name="min_weight" id="modal_min_weight" required placeholder="1.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold outline-none focus:border-sky-700">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-600 mb-1">Max Weight (kg)</label>
                        <input type="number" step="0.01" name="max_weight" id="modal_max_weight" placeholder="Leave empty for &infin;" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold outline-none focus:border-sky-700">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 mb-1">Extra Fee Added (Rs.) *</label>
                    <input type="number" step="0.01" name="extra_cost" id="modal_extra_cost" required placeholder="100.00" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-black text-slate-900 outline-none focus:border-sky-700">
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('weightModal')" class="px-4 py-2 rounded-xl text-slate-600 font-bold hover:bg-slate-100">Cancel</button>
                    <button type="submit" id="modalWeightSubmitBtn" class="bg-slate-900 hover:bg-black text-white font-bold px-5 py-2 rounded-xl uppercase tracking-wider shadow-sm">Save Slab</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: ADD PROVINCE / DISTRICT REGION    -->
    <!-- ========================================== -->
    <div id="regionModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden p-6 border border-slate-100">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-extrabold text-slate-900 text-sm">Add Location Region</h3>
                <button type="button" onclick="closeModal('regionModal')" class="text-slate-400 hover:text-rose-600 text-xl font-bold">&times;</button>
            </div>
            
            <form action="shipping_zones_action.php" method="POST" class="space-y-3.5 text-xs">
                <input type="hidden" name="action" value="save_region">
                <div>
                    <label class="block font-bold text-slate-600 mb-1">Region Type</label>
                    <select name="type" id="modal_reg_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold" onchange="toggleParentSelect(this.value)">
                        <option value="province">New Province (පළාත)</option>
                        <option value="district">New District (දිස්ත්‍රික්කය)</option>
                    </select>
                </div>

                <div id="modal_parent_group" class="hidden">
                    <label class="block font-bold text-slate-600 mb-1">Parent Province</label>
                    <select name="parent_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                        <?php foreach($provinces as $p): ?>
                            <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 mb-1">Name</label>
                    <input type="text" name="name" required placeholder="e.g. Western Province or Colombo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                </div>

                <div class="pt-2 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeModal('regionModal')" class="px-4 py-2 rounded-xl text-slate-600 font-bold hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="bg-slate-900 hover:bg-black text-white font-bold px-5 py-2 rounded-xl uppercase">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php if($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?= $messageType; ?>', title: '<?= addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>

        function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

        // ==========================================
        // RATE RULE POPUP LOGIC (ADD / EDIT)
        // ==========================================
        const modalProvEl = document.getElementById('modal_province_id');
        const modalDistEl = document.getElementById('modal_district_id');
        const allDistOpts = Array.from(modalDistEl.options);

        modalProvEl?.addEventListener('change', function() {
            filterDistricts(this.value);
        });

        function filterDistricts(chosenProvId, selectedDistId = '') {
            modalDistEl.innerHTML = '';
            allDistOpts.forEach(opt => {
                if (!opt.value || !chosenProvId || opt.getAttribute('data-parent') === String(chosenProvId)) {
                    const cloned = opt.cloneNode(true);
                    if (selectedDistId && cloned.value === String(selectedDistId)) {
                        cloned.selected = true;
                    }
                    modalDistEl.appendChild(cloned);
                }
            });
        }

        function toggleModalPriceFields(isFree) {
            const grp = document.getElementById('modal_price_group');
            const cost = document.getElementById('modal_cost');
            if (isFree) {
                grp.classList.add('opacity-40', 'pointer-events-none');
                cost.value = '0.00';
            } else {
                grp.classList.remove('opacity-40', 'pointer-events-none');
                if (cost.value === '0.00') cost.value = '350.00';
            }
        }

        function openRateModal(mode, data = null) {
            const modal = document.getElementById('rateModal');
            const title = document.getElementById('rateModalTitle');
            const btn = document.getElementById('modalRateSubmitBtn');

            if (mode === 'edit' && data) {
                title.innerText = 'Edit Delivery Rate Rule';
                btn.innerText = 'Update Rule';
                document.getElementById('modal_rate_id').value = data.id;
                document.getElementById('modal_method_title').value = data.method_title;
                document.getElementById('modal_country_id').value = data.country_id || 1;
                document.getElementById('modal_province_id').value = data.province_id || '';
                
                filterDistricts(data.province_id, data.district_id);
                
                if (data.shipping_type === 'free') {
                    document.getElementById('modal_type_free').checked = true;
                    toggleModalPriceFields(true);
                } else {
                    document.getElementById('modal_type_paid').checked = true;
                    toggleModalPriceFields(false);
                    document.getElementById('modal_cost').value = data.cost;
                }

                document.getElementById('modal_min_order_free').value = data.min_order_free || '';
                document.getElementById('modal_estimated_days').value = data.estimated_days || '2-3 Business Days';
            } else {
                title.innerText = 'Add Location Rate Rule';
                btn.innerText = 'Save Rule';
                document.getElementById('modal_rate_id').value = '';
                document.getElementById('modal_method_title').value = 'Standard Delivery';
                document.getElementById('modal_country_id').value = 1;
                document.getElementById('modal_province_id').value = '';
                filterDistricts('');
                document.getElementById('modal_type_paid').checked = true;
                toggleModalPriceFields(false);
                document.getElementById('modal_cost').value = '350.00';
                document.getElementById('modal_min_order_free').value = '';
                document.getElementById('modal_estimated_days').value = '2-3 Business Days';
            }

            openModal('rateModal');
        }

        // ==========================================
        // WEIGHT SLAB POPUP LOGIC (ADD / EDIT)
        // ==========================================
        function openWeightModal(mode, data = null) {
            const title = document.getElementById('weightModalTitle');
            const btn = document.getElementById('modalWeightSubmitBtn');

            if (mode === 'edit' && data) {
                title.innerText = 'Edit Weight Range Slab';
                btn.innerText = 'Update Slab';
                document.getElementById('modal_weight_id').value = data.id;
                document.getElementById('modal_weight_label').value = data.label || '';
                document.getElementById('modal_min_weight').value = data.min_weight || '0';
                document.getElementById('modal_max_weight').value = data.max_weight || '';
                document.getElementById('modal_extra_cost').value = data.extra_cost || '0.00';
            } else {
                title.innerText = 'Add Weight Range Slab';
                btn.innerText = 'Save Slab';
                document.getElementById('modal_weight_id').value = '';
                document.getElementById('modal_weight_label').value = '';
                document.getElementById('modal_min_weight').value = '';
                document.getElementById('modal_max_weight').value = '';
                document.getElementById('modal_extra_cost').value = '100.00';
            }

            openModal('weightModal');
        }

        function openRegionModal() {
            document.getElementById('modal_reg_type').value = 'province';
            toggleParentSelect('province');
            openModal('regionModal');
        }

        function toggleParentSelect(val) {
            const grp = document.getElementById('modal_parent_group');
            if (val === 'district') grp.classList.remove('hidden');
            else grp.classList.add('hidden');
        }
    </script>
</body>
</html>