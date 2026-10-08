# Refresh the Certicode Labs knowledge graph and re-export it to the Obsidian vault.
#
#   .\scripts\graphify\sync.ps1
#
# Re-extracts code (no LLM, no tokens) and reuses cached doc extractions. If you changed docs
# (README, TODO, ERD, design system), run this in Claude Code instead so they get
# re-read:  /graphify . --update --obsidian --obsidian-dir C:\Users\vheng\brain\graphify\certicode-labs
param(
    [string]$VaultDir = "$env:USERPROFILE\brain\graphify\certicode-labs"
)
$ErrorActionPreference = 'Stop'
$root = Resolve-Path "$PSScriptRoot\..\.."
Push-Location $root
try {
    $python = Get-Content graphify-out\.graphify_python

    # Not `graphify update`: it re-parses docs without the LLM and drops their extracted concepts.
    & $python scripts\graphify\rebuild.py
    if ($LASTEXITCODE -eq 2) { return }
    if ($LASTEXITCODE -ne 0) { throw "rebuild failed" }

    graphify export obsidian --dir $VaultDir
    if ($LASTEXITCODE -ne 0) { throw "obsidian export failed" }

    # The export drops a standalone-vault .obsidian/ folder; the notes live inside the main vault.
    $nested = Join-Path $VaultDir '.obsidian'
    if (Test-Path $nested) { Remove-Item -Recurse -Force $nested }

    graphify export html
}
finally {
    Pop-Location
}
