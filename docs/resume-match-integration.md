# Resume Match in E2E Tracking

## Open the feature

Open Recruiting and choose **Resume Match**, or visit /dashboard/recruiting/resume-match. It inherits the Recruiting resource's view permission and the existing E2E login. A custom recruiting resource route also gets the same resume-match subpage.

## Included features

- Paste a job description or upload PDF, DOCX, or TXT.
- Upload and remove multiple resumes (up to 50, 10 MB per file).
- Search the supplied, explicitly labelled demo profiles by keyword and US state.
- Rank candidates with the original skill catalogue, aliases, category weights, explanations, matched/missing skills, and category breakdown.
- Expand candidate details and extracted resume text.
- Per-user comparison history with a reusable job description.
- Responsive light/dark presentation and background-worker scoring.

Files are parsed in the browser. Resume documents and extracted candidate text are not sent to PHP. History stores the first 5,000 job-description characters, selected source/state, candidate count, and top score. History ownership is taken from the authenticated user; a submitted user ID cannot change it.

The original TechFetch fallback is now an explicit demo mode, as requested. No website passwords, Supabase settings, or TechFetch API credentials are required. Demo candidates are never presented as live search results. Live provider integration is deferred until official API access is supplied.

## Build and deployment

1. Run npm ci in the frontend and npm run build.
2. Deploy the complete build/ output. The prebuild hook copies the matching PDF worker to /resume-match/pdf.worker.min.mjs; serve .mjs as JavaScript and do not route that file to the SPA HTML fallback.
3. Deploy backend modules/resume_match/model.php, modules/resume_match/routes.php, and the updated backend index.php routing block.
4. Run migrations/20261013_resume_match.sql once on the existing E2E MySQL database. It creates only resume_match_history; it does not change users, employees, candidates, or jobs.
5. Keep REACT_APP_API_BASE_URL pointing at your E2E PHP server. For a live build, configure the live HTTPS API URL before running npm run build; keep localhost for local development.

The local migration has been applied. Live hosting needs the same migration and PHP module files. No additional backend dependencies or configuration secrets are needed.

## Verification commands

    npm test -- --watchAll=false --runInBand --runTestsByPath src/features/resumeMatch/multiResumeAnalyzer.test.js src/features/resumeMatch/fileParser.test.js src/pages/Dashboard/ResumeMatch.test.js
    npm run build

Backend verification:

    C:\xampp\php\php.exe C:\xampp\htdocs\E2E_Tracking\tests\resume_match_test.php

This covers scoring aliases and boundaries, rank order, parsing validation, demo/upload handling, stale-result clearing, history reuse, history ownership, request validation, and insert parameter counts. Backend HTTP verification also exercises JWT protection and saving/reading through Apache, cleaning up its temporary row.

Scanned image-only PDFs require OCR before upload, and legacy DOC files must be converted to DOCX or PDF. The score is a weighted keyword match, not an AI hiring decision.

## Source

The skill catalogue, scoring engine, and demonstration profiles were imported from the supplied Resume_Match_1-main project. The integrated runtime is independent of that folder and Supabase.


## Verification status and existing test debt

The focused Resume Match checks and browser checks pass, including actual PDF/DOCX parsing and background-worker scoring. The Apache endpoint check passes authentication, saving/reading, validation, and rejection of a forged history owner.

The existing repository-wide suite is not fully green: App.test.js has a React Router/Jest resolution problem, and an existing employee-profile test still expects Date of Joining to be readonly although the unchanged implementation allows editing. Slow five-second tests passed on retry with a larger timeout. The employee and attendance implementations were not changed by this integration. Existing unrelated lint warnings remain in the production build.

Build tools were tested with Node 20.20.2. Parser versions are pinned and the lockfile is included.

Final focused verification: 11 tests passed, including preservation of rankings and release of controls after a history-save failure. History API requests have a 15-second timeout. Browser checks passed for the recruiting button/subpage, actual PDF/DOCX parsing, scoring workers, candidate details, labelled demos, history, mobile sizing, and dark theme.
