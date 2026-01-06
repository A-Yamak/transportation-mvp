#!/bin/bash

# Git Worktree Setup for 3-Developer MVP Sprint (Jan 7, 2026)
# Transportation MVP - ERP Integration Focus

set -e  # Exit on error

echo "========================================="
echo "Setting up Git Worktrees for 3 Developers"
echo "Transportation MVP - ERP Integration Sprint"
echo "========================================="
echo ""

# Go to project root
cd "$(dirname "$0")"

echo "Current directory: $(pwd)"
echo ""

# Function to create worktree
create_worktree() {
    local folder=$1
    local branch=$2
    local description=$3

    echo "📁 Creating: $folder"
    echo "   Branch: $branch"
    echo "   Purpose: $description"

    # Check if folder already exists
    if [ -d "./$folder" ]; then
        echo "   ⚠️  Warning: Folder exists, removing..."
        git worktree remove "./$folder" --force 2>/dev/null || rm -rf "./$folder"
    fi

    # Delete branch if exists (to start fresh)
    git branch -D "$branch" 2>/dev/null || true

    # Create worktree with new branch from main
    git worktree add "./$folder" -b "$branch" main

    echo "   ✅ Done"
    echo ""
}

# Clean up old worktrees first
echo "Cleaning up old worktrees..."
git worktree prune
echo ""

# Create worktrees for 3 devs
echo "Creating worktrees..."
echo ""

create_worktree "dev-1-erp-integration" "feature/dev-1-erp-integration" "DeliveryRequestController, RouteOptimizer, Callbacks"
create_worktree "dev-2-driver-api" "feature/dev-2-driver-api" "Driver endpoints (7 routes), Trip management"
create_worktree "dev-3-flutter" "feature/dev-3-flutter" "Flutter app API integration, replace mock data"

echo "========================================="
echo "✅ All Worktrees Created Successfully!"
echo "========================================="
echo ""

# Show worktree list
echo "Current worktrees:"
git worktree list
echo ""

echo "Folder structure:"
ls -lhd dev-* 2>/dev/null || echo "No dev folders yet"
echo ""

echo "========================================="
echo "Next Steps:"
echo "========================================="
echo ""
echo "1. Open 3 terminal windows"
echo ""
echo "   Terminal 1 (DEV-1 ERP Integration):"
echo "   cd $(pwd)/dev-1-erp-integration"
echo "   claude"
echo ""
echo "   Terminal 2 (DEV-2 Driver API):"
echo "   cd $(pwd)/dev-2-driver-api"
echo "   claude"
echo ""
echo "   Terminal 3 (DEV-3 Flutter):"
echo "   cd $(pwd)/dev-3-flutter"
echo "   claude"
echo ""
echo "2. In each Claude session, paste the task file content"
echo "   Task files are in: mvp-tasks/"
echo ""
echo "========================================="
echo "🚀 Ready for 3-Developer MVP Sprint!"
echo "========================================="
