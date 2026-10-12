<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';

$userId = current_user_id();
$user   = current_user();

$analysisResult = null;
$rawInputText   = '';
$successMessage = '';
$errorMessage   = '';

// Handle Resume Analysis or 1-Click Portfolio Sync
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'analyze') {
        $extractedText = '';

        // 1. Check file upload (PDF, DOCX, TXT)
        if (!empty($_FILES['resume_file']['name']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['resume_file']['tmp_name'];
            $origName = $_FILES['resume_file']['name'];
            $extractedText = AIEngine::extractTextFromFile($tmpPath, $origName);
        }

        // 2. Check pasted text if no file or appended
        $pastedText = trim((string)($_POST['resume_text'] ?? ''));
        if ($pastedText !== '') {
            $extractedText = $extractedText !== '' ? ($extractedText . "\n\n" . $pastedText) : $pastedText;
        }

        $rawInputText = $extractedText;

        if (empty(trim($extractedText))) {
            $errorMessage = 'Please provide resume text by uploading a PDF, DOCX, or TXT file, or by pasting your resume content.';
        } else {
            $analysis = AIEngine::analyzeResume($extractedText, $userId);
            if (!empty($analysis['success']) && !empty($analysis['data'])) {
                $analysisResult = $analysis['data'];
                $_SESSION['last_resume_analysis'] = $analysisResult;
                $successMessage = 'ATS analysis completed successfully! Review your score, competencies, and optimizations below.';
            } else {
                $errorMessage = 'Unable to analyze resume. Please ensure the text contains readable career details or try plain text.';
            }
        }
    } elseif ($action === 'sync_to_portfolio') {
        // Sync previously analyzed data or post data to user_portfolios & user_vcards
        $stored = $_SESSION['last_resume_analysis'] ?? null;
        if (!$stored) {
            $errorMessage = 'No active analysis found to sync. Please run a resume analysis first.';
        } else {
            $cand = $stored['candidate'] ?? [];
            $flatSkills = $stored['flat_skills'] ?? [];
            $services = $stored['services'] ?? [];
            $projects = $stored['portfolio_items'] ?? [];

            $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $cand['email'] ?: $user['email'])[0])));
            if (empty($uname)) $uname = 'user_' . $userId;

            // 1. Sync user_portfolios
            $portfolioData = [
                'user_id'              => $userId,
                'username'             => $uname,
                'display_name'         => $cand['display_name'] ?: $user['full_name'],
                'job_title'            => $cand['job_title'] ?: 'Performance Advertiser & Creator',
                'headline'             => $cand['headline'] ?: '',
                'bio'                  => $cand['bio'] ?: '',
                'phone'                => $cand['phone'] ?: ($user['phone'] ?? ''),
                'email'                => $cand['email'] ?: $user['email'],
                'company'              => $cand['company'] ?: ($user['company'] ?? 'Adsbooster Partner Network'),
                'location'             => $cand['location'] ?: 'Worldwide / Remote',
                'website'              => $cand['website'] ?: BASE_URL . '/portfolio.php?u=' . urlencode($uname),
                'skills_json'          => json_encode(array_values($flatSkills)),
                'services_json'        => json_encode($services),
                'portfolio_items_json' => json_encode($projects),
                'is_public'            => 1,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            $existingPortfolio = db()->fetchOne("SELECT id FROM user_portfolios WHERE user_id = ?", [$userId]);
            if ($existingPortfolio) {
                db()->update('user_portfolios', $portfolioData, 'id = ?', [$existingPortfolio['id']]);
            } else {
                $portfolioData['template_style'] = 'dark_luxury';
                $portfolioData['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_portfolios', $portfolioData);
            }

            // 2. Sync user_vcards
            $vcardData = [
                'user_id'          => $userId,
                'username'         => $uname,
                'headline'         => $cand['headline'] ?: '',
                'bio'              => $cand['bio'] ?: '',
                'phone'            => $cand['phone'] ?: ($user['phone'] ?? ''),
                'whatsapp'         => $cand['phone'] ?: ($user['phone'] ?? ''),
                'company'          => $cand['company'] ?: ($user['company'] ?? 'Adsbooster.net'),
                'job_title'        => $cand['job_title'] ?: 'Performance Marketer',
                'location'         => $cand['location'] ?: 'Worldwide / Remote',
                'website'          => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                'skills_json'      => json_encode(array_values($flatSkills)),
                'projects_json'    => json_encode($projects),
                'is_public'        => 1,
                'updated_at'       => date('Y-m-d H:i:s'),
            ];

            $existingVcard = db()->fetchOne("SELECT id FROM user_vcards WHERE user_id = ?", [$userId]);
            if ($existingVcard) {
                db()->update('user_vcards', $vcardData, 'id = ?', [$existingVcard['id']]);
            } else {
                $vcardData['template'] = 'dark_luxury';
                $vcardData['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_vcards', $vcardData);
            }

            // 3. Update users table headline & company
            db()->update('users', [
                'headline' => $cand['headline'] ?: '',
                'bio'      => $cand['bio'] ?: '',
                'company'  => $cand['company'] ?: ($user['company'] ?? ''),
            ], 'id = ?', [$userId]);

            $analysisResult = $stored;
            $successMessage = '✨ 1-Click Sync Complete! Your Public Portfolio and Digital vCard have been updated with your ATS-optimized profile, skills, and accomplishments.';
        }
    }
} elseif (isset($_SESSION['last_resume_analysis']) && is_array($_SESSION['last_resume_analysis'])) {
    $analysisResult = $_SESSION['last_resume_analysis'];
}

$pageTitle = 'AI Resume Analyser & ATS Optimizer — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'resume-analyser';
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="container-fluid px-0">

    <!-- Header Banner -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1 font-size-11 fw-bold" style="background: rgba(200,255,0,0.12); color: var(--accent); border: 1px solid rgba(200,255,0,0.3);">
                    <i class="ph-bold ph-magic-wand me-1"></i> Groq LPU &amp; ATS Algorithm Auditor
                </span>
                <span class="text-white-50 font-size-12">Recruiter Screening Optimizer</span>
            </div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph-bold ph-file-text text-accent me-2"></i> AI Resume Analyser &amp; ATS Optimizer
            </h1>
            <p class="text-muted mb-0 font-size-14">
                Audit your resume against modern Applicant Tracking Systems, discover high-impact keyword gaps, and sync your profile into your shareable Digital vCard &amp; Portfolio with one click.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/portfolio-builder.php" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                <i class="ph ph-paint-brush"></i> Portfolio Studio
            </a>
            <a href="<?= BASE_URL ?>/vcard.php" target="_blank" class="btn btn-accent rounded-pill px-4 py-2 font-size-13 text-dark fw-bold d-flex align-items-center gap-2">
                <i class="ph-bold ph-address-book"></i> View My vCard
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph-bold ph-check-circle font-size-20"></i>
                <span><?= e($successMessage) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/portfolio.php" target="_blank" class="btn btn-sm btn-success rounded-pill px-3 py-1 font-size-12 fw-bold text-dark">
                    View Portfolio <i class="ph ph-arrow-square-out ms-1"></i>
                </a>
                <a href="<?= BASE_URL ?>/vcard.php" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 font-size-12">
                    View Digital Card <i class="ph ph-address-book ms-1"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4 d-flex align-items-center gap-2">
            <i class="ph-bold ph-warning-circle font-size-20"></i>
            <span><?= e($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Grid: Uploader & Live Results -->
    <div class="row g-4">

        <!-- Left Column: Resume Input Form -->
        <div class="col-lg-5">
            <div class="card-dark rounded-4 p-4 h-100" style="background: #0d1524 !important; border: 1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="h5 text-white fw-bold mb-0">Input Resume / CV</h3>
                    <button type="button" onclick="loadSampleResume()" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-11">
                        <i class="ph ph-lightning me-1"></i> Load Sample
                    </button>
                </div>

                <p class="font-size-13 text-white-50 mb-3" style="line-height: 1.5;">
                    Upload a file or paste your resume text. Our AI extracts your career history, quantifies your commercial achievements, and calculates your ATS readiness score.
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="analyze">

                    <!-- File Dropzone -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold font-size-13 text-white">Upload Resume File (.PDF, .DOCX, .TXT)</label>
                        <div class="p-4 rounded-4 text-center cursor-pointer position-relative" style="background:#080c14;border:2px dashed rgba(255,255,255,0.15);transition:border-color 0.2s;" id="dropzoneBox">
                            <input type="file" name="resume_file" id="resumeFileInput" accept=".pdf,.docx,.txt" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" onchange="handleFileSelect(this)">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(200,255,0,0.12);display:inline-flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:10px;">
                                <i class="ph-bold ph-cloud-arrow-up font-size-24"></i>
                            </div>
                            <div class="text-white fw-bold font-size-13 mb-1" id="fileLabelTitle">Choose file or drag &amp; drop</div>
                            <div class="text-white-50 font-size-11" id="fileLabelSub">Supported formats: PDF, Microsoft Word (.docx), Plain Text</div>
                        </div>
                    </div>

                    <!-- Direct Textarea -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold font-size-13 text-white">Or Paste Resume Content</label>
                        <textarea name="resume_text" id="resumeTextArea" rows="7" class="form-control font-size-12 font-monospace" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:12px;line-height:1.6;" placeholder="Paste raw resume text, LinkedIn profile export, or experience summary here..."><?= e($rawInputText) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-accent rounded-pill w-100 py-3 fw-bold text-dark font-size-14 d-flex align-items-center justify-content-center gap-2">
                        <i class="ph-bold ph-cpu font-size-18"></i> Run Deep ATS Analysis
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: ATS Report Dashboard -->
        <div class="col-lg-7">
            <?php if (!empty($analysisResult)): ?>
                <?php
                    $atsScore   = (int)($analysisResult['ats_score'] ?? 85);
                    $subScores  = $analysisResult['sub_scores'] ?? [];
                    $candidate  = $analysisResult['candidate'] ?? [];
                    $skills     = $analysisResult['skills'] ?? [];
                    $flatSkills = $analysisResult['flat_skills'] ?? [];
                    $strengths  = $analysisResult['strengths'] ?? [];
                    $missingKw  = $analysisResult['missing_keywords'] ?? [];
                    $improvements = $analysisResult['actionable_improvements'] ?? [];
                    $portfolioItems = $analysisResult['portfolio_items'] ?? [];

                    $gaugeColor = $atsScore >= 85 ? '#c8ff00' : ($atsScore >= 75 ? '#38bdf8' : '#f59e0b');
                    $strokeDash = (int)(2 * M_PI * 45); // Circumference for r=45 is ~282.7
                    $strokeOffset = $strokeDash - ($strokeDash * $atsScore / 100);
                ?>

                <!-- Score Banner Card -->
                <div class="card-dark rounded-4 p-4 mb-4 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                    <div class="row align-items-center g-4">
                        <!-- Circular SVG Gauge -->
                        <div class="col-sm-auto text-center">
                            <div class="position-relative d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg width="120" height="120" viewBox="0 0 100 100" class="transform -rotate-90">
                                    <circle cx="50" cy="50" r="45" stroke="rgba(255,255,255,0.1)" stroke-width="8" fill="none" />
                                    <circle cx="50" cy="50" r="45" stroke="<?= $gaugeColor ?>" stroke-width="8" stroke-dasharray="<?= $strokeDash ?>" stroke-dashoffset="<?= $strokeOffset ?>" stroke-linecap="round" fill="none" style="transition: stroke-dashoffset 1s ease;" />
                                </svg>
                                <div class="position-absolute text-center">
                                    <span class="text-white fw-bold font-size-26 d-block" style="line-height:1;"><?= $atsScore ?></span>
                                    <span class="font-size-11 text-white-50">/ 100</span>
                                </div>
                            </div>
                        </div>

                        <!-- Score Details -->
                        <div class="col-sm">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="badge rounded-pill px-3 py-1 font-size-12 fw-bold" style="background: <?= $gaugeColor ?>20; color: <?= $gaugeColor ?>; border: 1px solid <?= $gaugeColor ?>50;">
                                    GRADE: <?= e($analysisResult['grade'] ?? 'A') ?>
                                </span>
                                <span class="text-white fw-bold font-size-14"><?= e($analysisResult['verdict'] ?? 'ATS Ready') ?></span>
                            </div>
                            <p class="text-white-75 font-size-13 mb-3">
                                Candidate: <strong class="text-white"><?= e($candidate['display_name'] ?? 'Candidate') ?></strong> &bull;
                                Target Role: <span style="color: var(--accent);"><?= e($candidate['job_title'] ?? 'Growth Marketer') ?></span>
                            </p>

                            <!-- Sub-Metrics Bars -->
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="d-flex justify-content-between font-size-11 text-white-50 mb-1">
                                        <span>Readability</span>
                                        <span class="text-white"><?= (int)($subScores['readability'] ?? 90) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-success" style="width: <?= (int)($subScores['readability'] ?? 90) ?>%;"></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between font-size-11 text-white-50 mb-1">
                                        <span>Keyword Density</span>
                                        <span class="text-white"><?= (int)($subScores['keyword_optimization'] ?? 85) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-info" style="width: <?= (int)($subScores['keyword_optimization'] ?? 85) ?>%;"></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between font-size-11 text-white-50 mb-1">
                                        <span>Impact &amp; Metrics</span>
                                        <span class="text-white"><?= (int)($subScores['impact_metrics'] ?? 88) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-warning" style="width: <?= (int)($subScores['impact_metrics'] ?? 88) ?>%;"></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between font-size-11 text-white-50 mb-1">
                                        <span>ATS Structure</span>
                                        <span class="text-white"><?= (int)($subScores['structure'] ?? 92) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-accent" style="width: <?= (int)($subScores['structure'] ?? 92) ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1-Click Sync CTA Bar -->
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-15 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="font-size-13 text-white-75">
                            <i class="ph-bold ph-sparkle text-accent me-1"></i> Apply these ATS-optimized results to your public portfolio &amp; vCard?
                        </span>
                        <form method="POST" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="sync_to_portfolio">
                            <button type="submit" class="btn btn-accent rounded-pill px-4 py-2 font-size-13 fw-bold text-dark d-flex align-items-center gap-1">
                                <i class="ph-bold ph-arrows-clockwise"></i> 1-Click Sync to Portfolio &amp; vCard
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Extracted Profile & Bio -->
                <div class="card-dark rounded-4 p-4 mb-4 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="h6 text-white fw-bold mb-0">
                            <i class="ph-bold ph-identification-badge text-accent me-1"></i> AI-Polished Executive Summary
                        </h4>
                        <button type="button" onclick="navigator.clipboard.writeText('<?= addslashes($candidate['bio'] ?? '') ?>'); alert('Bio copied to clipboard!');" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-11">
                            <i class="ph ph-copy me-1"></i> Copy Bio
                        </button>
                    </div>
                    <p class="font-size-14 text-white fw-semibold mb-2" style="color: var(--accent) !important;">
                        "<?= e($candidate['headline'] ?? '') ?>"
                    </p>
                    <p class="font-size-13 text-white-75 mb-3" style="line-height: 1.7;">
                        <?= nl2br(e($candidate['bio'] ?? '')) ?>
                    </p>

                    <!-- Extracted Contact Details -->
                    <div class="d-flex flex-wrap gap-3 font-size-12 text-white-50 pt-2 border-top border-secondary border-opacity-15">
                        <?php if (!empty($candidate['email'])): ?>
                            <div><i class="ph ph-envelope-simple text-white-75 me-1"></i> <?= e($candidate['email']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($candidate['phone'])): ?>
                            <div><i class="ph ph-phone text-white-75 me-1"></i> <?= e($candidate['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($candidate['location'])): ?>
                            <div><i class="ph ph-map-pin text-white-75 me-1"></i> <?= e($candidate['location']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($candidate['company'])): ?>
                            <div><i class="ph ph-buildings text-white-75 me-1"></i> <?= e($candidate['company']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Skills Breakdown -->
                <div class="card-dark rounded-4 p-4 mb-4 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                    <h4 class="h6 text-white fw-bold mb-3">
                        <i class="ph-bold ph-check-square-offset text-accent me-1"></i> Core Competencies &amp; Platforms Found
                    </h4>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($flatSkills as $sk): ?>
                            <span class="badge rounded-pill px-3 py-2 font-size-12 fw-semibold" style="background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
                                <i class="ph-bold ph-check text-accent me-1"></i> <?= e($sk) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Case Studies & Quantified Results -->
                <?php if (!empty($portfolioItems)): ?>
                    <div class="card-dark rounded-4 p-4 mb-4 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                        <h4 class="h6 text-white fw-bold mb-3">
                            <i class="ph-bold ph-trophy text-accent me-1"></i> Extracted Quantifiable Case Studies
                        </h4>
                        <div class="row g-3">
                            <?php foreach ($portfolioItems as $item): ?>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3" style="background: #080c14; border: 1px solid rgba(255,255,255,0.1);">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="text-white fw-bold font-size-13"><?= e($item['title'] ?? 'Campaign Result') ?></span>
                                            <?php if (!empty($item['metric'])): ?>
                                                <span class="badge bg-main-600 text-dark fw-bold rounded-pill px-2 py-1 font-size-11">
                                                    <?= e($item['metric']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-white-50 font-size-12 mb-0" style="line-height: 1.5;">
                                            <?= e($item['description'] ?? '') ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ATS Audit: Strengths, Missing Keywords & Actionable Advice -->
                <div class="row g-3">
                    <!-- Missing High-Value Keywords -->
                    <div class="col-md-6">
                        <div class="card-dark rounded-4 p-4 h-100 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                            <h4 class="h6 text-warning fw-bold mb-2">
                                <i class="ph-bold ph-warning-diamond me-1"></i> Missing High-Impact Keywords
                            </h4>
                            <p class="font-size-12 text-white-50 mb-3">Adding these target terms will increase your ATS screening pass rate:</p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($missingKw as $kw): ?>
                                    <span class="badge rounded-pill px-3 py-1 font-size-11" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">
                                        + <?= e($kw) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actionable ATS Recommendations -->
                    <div class="col-md-6">
                        <div class="card-dark rounded-4 p-4 h-100 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                            <h4 class="h6 text-accent fw-bold mb-2">
                                <i class="ph-bold ph-lightbulb me-1"></i> Actionable ATS Improvements
                            </h4>
                            <ul class="font-size-12 text-white-75 ps-3 mb-0" style="line-height: 1.6;">
                                <?php foreach ($improvements as $imp): ?>
                                    <li class="mb-1"><?= e($imp) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Empty Placeholder State -->
                <div class="card-dark rounded-4 p-5 text-center h-100 d-flex flex-column align-items-center justify-content-center" style="background: #0d1524 !important; border: 1px dashed rgba(255,255,255,0.12) !important; min-height: 480px;">
                    <div style="width:72px;height:72px;border-radius:20px;background:rgba(200,255,0,0.1);display:inline-flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:18px;">
                        <i class="ph-bold ph-gauge font-size-36"></i>
                    </div>
                    <h3 class="h4 text-white fw-bold mb-2">No Active Analysis Yet</h3>
                    <p class="font-size-14 text-white-50 max-w-450-px mb-4" style="line-height: 1.6;">
                        Upload your resume in PDF or Word DOCX format, or paste your career summary on the left to receive an instant ATS score, keyword audit, and 1-click sync into your portfolio.
                    </p>
                    <button type="button" onclick="loadSampleResume()" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13">
                        <i class="ph-bold ph-file-text me-1"></i> Load Sample Media Buyer Resume
                    </button>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('fileLabelTitle').innerText = file.name;
        document.getElementById('fileLabelSub').innerText = (file.size / 1024).toFixed(1) + ' KB — Ready for analysis';
        document.getElementById('dropzoneBox').style.borderColor = 'var(--accent)';
    }
}

function loadSampleResume() {
    const sample = `Alex Rivera
Senior Omnichannel Media Buyer & Creative Strategist
Email: alex.rivera@adsbooster.net | Phone: +1 (555) 019-2831 | New York, NY / Remote
Portfolio: https://adsbooster.net/portfolio.php?u=alexrivera

SUMMARY:
Results-driven Performance Marketing Director with 6+ years orchestrating profitable omnichannel ad campaigns across Meta Ads, TikTok Ads, and Google Ads. Managed over $3.2M in annual ad spend, delivering an average blended ROAS of 4.4x for fast-growing DTC eCommerce and B2B SaaS brands. Expert in UGC creative iteration, Landing Page CRO, and full-funnel attribution modeling.

CORE SKILLS:
Meta Ads Manager, TikTok Ads Manager, Google Performance Max, UGC Creative Testing, Copywriting, Conversion Rate Optimization (CRO), Shopify Plus, Triple Whale Attribution, Klaviyo Retention Marketing, Budget Allocation.

PROVEN CAMPAIGN HIGHLIGHTS:
1. Omnichannel DTC Apparel Brand Scaling: Scaled monthly ad spend from $14k to $145k across Meta and TikTok while increasing blended ROAS from 3.1x to 4.8x.
2. B2B SaaS Acquisition Sprint: Revamped creative hooks and landing page sales copy, slashing cost-per-qualified-lead (CPA) by 41% and driving $320k in pipeline value.

SERVICES OFFERED:
- Full Omnichannel Media Buying Retainer: $2,500/month
- High-Converting UGC Creative Testing Sprint: $750/campaign
- Paid Ads Account & CRO Audit: $450/audit`;

    document.getElementById('resumeTextArea').value = sample;
    document.getElementById('resumeTextArea').focus();
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
