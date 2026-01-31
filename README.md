# LaraLedger

**A release decision assistant for developers who care about semantic versioning.**

LaraLedger helps you answer the one painful question developers face with every release:

> "Is this a PATCH, MINOR, or MAJOR release... and am I about to break someone's project?"

---

## What LaraLedger Is (And Isn't)

LaraLedger is a **release decision assistant**.

| It IS | It is NOT |
|-------|-----------|
| A self-hosted Laravel utility | A SaaS product |
| Read-only GitHub intelligence | A CI/CD tool |
| A recommendation engine | Auto-publishing |
| An audit trail for decisions | A bot that pushes versions |

Everything in LaraLedger exists to make your release decisions:

- **Safer** — Catch breaking changes before they ship
- **Faster** — Skip the mental overhead of analyzing commits
- **Auditable** — Know why every version was chosen
- **Explainable** — Show stakeholders the reasoning

---

## How It Works

### Step 1: Install It

LaraLedger is a self-hosted Laravel application. SQLite database. Single user authentication. It feels like a Laravel dev utility, not enterprise software.

### Step 2: Connect GitHub

OAuth with GitHub and select your repositories. LaraLedger:

- Stores repository references
- Remembers your tags and branches
- Does **NOT** push code
- Does **NOT** auto-release

This is **read-only intelligence**.

### Step 3: Analyze a Release

When you're ready to cut a release, click "Analyze" and select your ref range (e.g., `v1.2.0` → `main`).

Behind the scenes:

1. Fetches commits between refs
2. Runs **heuristics first** (fast, free)
3. Only calls AI **if things are unclear**

This is important:
- AI is not always on
- Cost is controlled
- This feels engineered, not magical

### Step 4: Review the Recommendation

This is the core value moment. You see:

- **Recommended version**: PATCH / MINOR / MAJOR
- **Confidence score**: e.g., 92%
- **Reasoning**: File changes, commit analysis, heuristic signals, AI notes
- **Draft release notes**: Ready to copy

Now you choose:

| Action | When to use |
|--------|-------------|
| **Accept** | The recommendation looks right (most common) |
| **Adjust** | You know better — override with your version |
| **Reject** | Wrong refs or bad analysis (rare, but logged) |

If you adjust, **you must say why**. That's intentional.

### Step 5: Build Release Intelligence

Every decision is logged:

- What was recommended vs. what you chose
- Why you overrode it (if you did)
- Confidence vs. reality over time

This transforms LaraLedger from an "AI opinion generator" into a **release intelligence system** that learns your patterns.

---

## Why This Matters

### 1. Removes Release Anxiety

You know this feeling:

> "This feels like a minor... but what if it breaks someone?"

LaraLedger doesn't replace your judgment. It **backs it up** with data.

### 2. Saves Time on Release Notes

Not glamorous, but real. LaraLedger auto-drafts:

- Human-readable release notes
- Grouped by change type (breaking, features, fixes)
- Exportable as Markdown

That's 10–20 minutes saved per release.

### 3. Creates an Audit Trail

Weeks later you can answer:

- "Why was this marked as MINOR?"
- "What changed in v2.3.0?"
- "What was the confidence at the time?"

Professional-grade tooling for your release process.

### 4. Makes AI Feel Safe

LaraLedger does something most AI tools don't: **it measures itself**.

- Acceptance rate tracking
- Breaking change detection accuracy
- Confidence calibration over time

That builds trust with senior developers who are (rightfully) skeptical of AI.

---

## Who Should Use This

### Ideal Users

- **Laravel package maintainers** — You ship code others depend on
- **OSS maintainers** — Your versioning affects downstream projects
- **Agency developers** — You maintain shared libraries across clients
- **Solo developers with paying users** — Breaking changes cost you customers
- **Teams that care about semver** — You want consistency and accountability

These are people who already worry about releases. LaraLedger gives them confidence.

### Not For

- Projects that don't follow semantic versioning
- Teams that want fully automated releases
- Applications (vs. packages/libraries) where versioning is cosmetic

---

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ & npm
- A GitHub account

---

## Installation

### 1. Create the Project

```bash
composer create-project --prefer-dist laraledger/laraledger
cd laraledger
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Set Up GitHub OAuth

Create a GitHub OAuth App at [github.com/settings/developers](https://github.com/settings/developers):

- **Application name**: LaraLedger (or whatever you prefer)
- **Homepage URL**: `http://localhost:8000` (or your domain)
- **Authorization callback URL**: `http://localhost:8000/auth/github/callback`

Add the credentials to your `.env`:

```env
GITHUB_CLIENT_ID=your_client_id
GITHUB_CLIENT_SECRET=your_client_secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```

### 4. Configure AI (Optional)

LaraLedger works without AI — heuristics handle most cases. But for ambiguous commits, AI classification improves accuracy.

```env
# Anthropic (recommended)
ANTHROPIC_API_KEY=your_api_key

# Or OpenAI
OPENAI_API_KEY=your_api_key
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Build Frontend Assets

```bash
npm install
npm run build
```

### 7. Start the Server

```bash
php artisan serve
```

Visit `http://localhost:8000`, register an account, and connect your GitHub.

---

## Configuration

### Heuristic Settings

Configure in `config/laraledger.php` or via the Settings UI:

| Setting | Default | Description |
|---------|---------|-------------|
| `confidence_threshold` | 85 | Minimum confidence before triggering AI |
| `always_use_ai` | false | Force AI classification for all analyses |
| `ignored_paths` | `[]` | Paths to exclude from analysis (e.g., `docs/*`) |

### AI Providers

LaraLedger supports multiple AI providers via [Prism](https://github.com/echolabsdev/prism):

- Anthropic (Claude)
- OpenAI (GPT-4)
- Ollama (local models)
- Mistral
- Groq

Configure your preferred provider in Settings → LaraLedger.

---

## Usage

### Adding a Repository

1. Go to **Repositories** → **Add Repository**
2. Select from your GitHub repos or enter a public repo name
3. Configure ignored paths if needed

### Running an Analysis

1. Open a repository
2. Click **Analyze Release**
3. Select your "from" ref (usually the last tag) and "to" ref (usually `main`)
4. Click **Preview Changes** to see what will be analyzed
5. Click **Start Analysis**

### Reviewing Results

After analysis completes:

1. Review the **recommended version** and **confidence score**
2. Read the **reasoning** — understand why this recommendation was made
3. Check **categorized changes** — breaking, features, fixes
4. Review **draft release notes**
5. Choose: **Accept**, **Adjust**, or **Reject**

### Viewing Analytics

Go to **Analytics** to see:

- Acceptance rate over time
- Version type distribution
- Confidence calibration
- Adjustment patterns

---

## Development

### Running Locally

```bash
# Start all services
composer run dev

# Or individually
php artisan serve
npm run dev
```

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
# Fix formatting
vendor/bin/pint
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                      LaraLedger                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐ │
│  │   GitHub    │───▶│  Heuristic  │───▶│     AI      │ │
│  │   Service   │    │  Analyzer   │    │ Classifier  │ │
│  └─────────────┘    └─────────────┘    └─────────────┘ │
│         │                  │                  │        │
│         ▼                  ▼                  ▼        │
│  ┌─────────────────────────────────────────────────┐   │
│  │              Analysis Service                    │   │
│  │  • Fetches commits                              │   │
│  │  • Runs heuristics (fast, free)                 │   │
│  │  • Calls AI only if confidence < threshold      │   │
│  │  • Generates release notes                      │   │
│  └─────────────────────────────────────────────────┘   │
│                          │                              │
│                          ▼                              │
│  ┌─────────────────────────────────────────────────┐   │
│  │                 Release Record                   │   │
│  │  • Recommendation + confidence                  │   │
│  │  • User decision (accept/adjust/reject)         │   │
│  │  • Feedback for learning                        │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Key Services

| Service | Purpose |
|---------|---------|
| `GitHubService` | Fetches commits, tags, branches via GitHub API |
| `HeuristicAnalyzer` | Fast, rule-based commit classification |
| `AIClassifier` | LLM-powered classification for ambiguous cases |
| `NoteGenerator` | Generates release notes in multiple styles |
| `AnalysisService` | Orchestrates the full analysis pipeline |

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

---

## Security

If you discover a security vulnerability, please email security@laraledger.dev instead of using the issue tracker.

---

## License

LaraLedger is open-sourced software licensed under the [MIT license](LICENSE).

---

## Credits

Built with:

- [Laravel](https://laravel.com) — The PHP framework for web artisans
- [Inertia.js](https://inertiajs.com) — Modern monolith architecture
- [Vue.js](https://vuejs.org) — Progressive JavaScript framework
- [Tailwind CSS](https://tailwindcss.com) — Utility-first CSS
- [Prism](https://github.com/echolabsdev/prism) — Multi-provider AI integration
- [shadcn/ui](https://ui.shadcn.com) — Beautiful UI components

---

<p align="center">
  <strong>Stop guessing. Start shipping with confidence.</strong>
</p>
