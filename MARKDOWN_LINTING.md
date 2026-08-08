# Markdown Linting Setup

This project uses markdownlint-cli2 to enforce consistent markdown formatting across all documentation files.

## Installation

Markdown linting dependencies are managed via npm. Install them with:

```bash
npm install
```

This will install:

- `markdownlint-cli2` - CLI tool for linting markdown files

## Configuration

Markdown linting rules are defined in `.markdownlint.yml`:

- **MD013**: Maximum line length of 180 characters (excluding code blocks and tables)
- **MD033**: Allows specific HTML elements (img, a, b, table, etc.)
- **MD041**: Disabled (allows non-heading first line)
- **MD046**: Enforces fenced code blocks (triple backticks)
- **MD029**: Enforces consistent ordered list numbering
- **MD025**: Configured for front matter

See `.markdownlint.yml` for complete configuration.

## Usage

### Manual Linting

Check all markdown files for issues:

```bash
composer lint:md
# or directly with npm
npm run lint:md
```

### Auto-fix Issues

Automatically fix many markdown issues:

```bash
composer lint:md:fix
# or directly with npm
npm run lint:md:fix
```

### Formatting (Prettier)

Two tools, deliberately kept separate:

| Tool                | Scripts                      | Owns                                                  |
| ------------------- | ---------------------------- | ----------------------------------------------------- |
| `markdownlint-cli2` | `lint:md`, `lint:md:fix`     | the `.markdownlint.yml` rules — MD013, MD029, MD040 … |
| `prettier`          | `format:md`, `format:md:fix` | formatting — table alignment (MD060), blank lines     |

They are complementary, not alternatives, because **`markdownlint-cli2 --fix` cannot repair MD060.**
Run it on a misaligned table and it reports the error but changes nothing; alignment would otherwise
have to be done by hand. `composer format:md:fix` does it mechanically, and its output passes
`lint:md` with zero errors. Prettier does **not** fix MD013 line length — that still needs a human
edit, because it runs with `--prose-wrap=preserve`.

Run prettier **first**, then markdownlint:

```bash
composer format:md:fix
composer lint:md
```

**Prettier is configured for markdown only, on purpose.** The scripts pass
`--embedded-language-formatting=off` so fenced PHP samples in the docs are left exactly as written,
rather than reformatted against prettier's defaults instead of the PSR-12 ruleset phpcs enforces. For
the same reason there is deliberately **no `.prettierrc`** — a config file would be picked up by an
editor's format-on-save and would start silently reformatting `src/`. Keep the options as CLI flags
in the scripts, and keep source out via `.prettierignore`.

### Excluded Directories

The following directories are automatically excluded from linting:

- `vendor/` - Composer dependencies
- `node_modules/` - npm dependencies

## Git Hooks (CaptainHook)

Markdown linting is automatically enforced via CaptainHook pre-commit hooks.

### Pre-Commit Hook

When you commit changes that include markdown files (`*.md`), the pre-commit hook will:

1. Run `composer lint:md` on all staged markdown files
1. Block the commit if linting errors are found
1. Display errors with file locations and rule violations

### Hook Configuration

The markdown linting hook is configured in `captainhook.json`:

```json
{
    "action": "composer lint:md",
    "conditions": [
        {
            "exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\Any",
            "args": [
                ["*.md"]
            ]
        }
    ]
}
```

This ensures markdown linting only runs when `.md` files are staged for commit.

### Workflow Example

```bash
# Make changes to markdown files
vim README.md

# Stage the changes
git add README.md

# Attempt to commit
git commit -m "Update README"

# If linting errors exist, the commit will be blocked:
# Summary: 5 error(s)
# README.md:28:181 MD013/line-length Line length [Expected: 180; Actual: 208]
# ...

# Fix the errors manually or use auto-fix
composer lint:md:fix

# Re-stage and commit
git add README.md
git commit -m "Update README"
```

## Common Linting Errors

### MD013 - Line Too Long

**Error**: Line length exceeds 180 characters

**Fix**: Break long lines into multiple lines, or use line breaks in paragraphs.

**Note**: This rule excludes code blocks and tables.

### MD031 - Fenced Code Blocks Need Blank Lines

**Error**: Fenced code blocks should be surrounded by blank lines

**Fix**: Add blank lines before and after code blocks:

````markdown
Some text here.

```bash
command here
```

More text here.
````

### MD040 - Fenced Code Should Have Language

**Error**: Fenced code blocks should specify a language

**Fix**: Add language identifier after opening backticks:

````markdown
```bash
#!/bin/bash
echo "Hello"
```
````

### MD032 - Lists Need Blank Lines

**Error**: Lists should be surrounded by blank lines

**Fix**: Add blank lines before and after lists:

```markdown
Some text here.

- List item 1
- List item 2
- List item 3

More text here.
```

## Integration with Other Linting

The project uses multiple linting tools:

### Pre-Commit Hook

- **PHP Linting**: Built-in PHP syntax checking
- **PHPCS**: PHP code style checking (`composer lint`)
- **Markdown**: Markdown formatting (`composer lint:md`)

### Pre-Push Hook

- **PHP Parallel Lint**: Parallel PHP syntax checking
- **PHPStan**: Static analysis (Level 10)

## Disabling Hooks (Not Recommended)

To skip hooks temporarily (not recommended for regular use):

```bash
# Skip pre-commit hooks
git commit --no-verify

# Skip pre-push hooks
git push --no-verify
```

**Warning**: Skipping hooks may result in commits that fail CI/CD checks.

## Updating Markdown Rules

To modify markdown linting rules:

1. Edit `.markdownlint.yml`
1. Run `composer lint:md` to verify changes
1. Commit the updated configuration

## Troubleshooting

### Hook Not Running

If the markdown linting hook doesn't run:

```bash
# Reinstall captainhook hooks
vendor/bin/captainhook install -f
```

### npm Command Not Found

If `composer lint:md` fails with "npm: command not found":

```bash
# Install Node.js and npm
# On Ubuntu/Debian:
sudo apt-get install nodejs npm

# On macOS with Homebrew:
brew install node

# Then install dependencies:
npm install
```

### Too Many Errors

If you have many markdown files with errors:

```bash
# Auto-fix what can be fixed automatically
composer lint:md:fix

# Review remaining errors
composer lint:md

# Fix remaining errors manually
```

## See Also

- **CLAUDE.md** - Markdown standards section
- **captainhook.json** - Complete hook configuration
- **.markdownlint.yml** - Linting rules configuration
- **package.json** - npm dependencies and scripts

---

**Last Updated**: 2025-11-18
