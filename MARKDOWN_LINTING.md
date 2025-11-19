# Markdown Linting Setup

This project uses markdownlint-cli2 to enforce consistent markdown formatting across all documentation files.

## Installation

Markdown linting dependencies are managed via npm. Install them with:

```bash
npm install
```text
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
```text
### Auto-fix Issues

Automatically fix many markdown issues:

```bash
composer lint:md:fix
# or directly with npm
npm run lint:md:fix
```text
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
```text
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
```text
## Common Linting Errors

### MD013 - Line Too Long

**Error**: Line length exceeds 180 characters

**Fix**: Break long lines into multiple lines, or use line breaks in paragraphs.

**Note**: This rule excludes code blocks and tables.

### MD031 - Fenced Code Blocks Need Blank Lines

**Error**: Fenced code blocks should be surrounded by blank lines

**Fix**: Add blank lines before and after code blocks:

```markdown
Some text here.

```bash
command here
```text
More text here.

```text
### MD040 - Fenced Code Should Have Language

**Error**: Fenced code blocks should specify a language

**Fix**: Add language identifier after opening backticks:

```markdown
```bash
#!/bin/bash
echo "Hello"
```text
```text
### MD032 - Lists Need Blank Lines

**Error**: Lists should be surrounded by blank lines

**Fix**: Add blank lines before and after lists:

```markdown
Some text here.

- List item 1
- List item 2
- List item 3

More text here.
```text
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
```text
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
```text
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
```text
### Too Many Errors

If you have many markdown files with errors:

```bash
# Auto-fix what can be fixed automatically
composer lint:md:fix

# Review remaining errors
composer lint:md

# Fix remaining errors manually
```text
## See Also

- **CLAUDE.md** - Markdown standards section
- **captainhook.json** - Complete hook configuration
- **.markdownlint.yml** - Linting rules configuration
- **package.json** - npm dependencies and scripts

---

**Last Updated**: 2025-11-18
