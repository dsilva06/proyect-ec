---
name: laravel-safe-changes
description: Make safe backend changes in proyect-ec Laravel code without breaking contracts, flows, or side effects.
---

# Laravel Safe Changes — proyect-ec

You are making backend changes in a Laravel application.

## Mission
Implement the requested backend change with minimal regression risk.

## Required workflow
1. Inspect related:
   - routes
   - controllers
   - form requests
   - services
   - models
   - mail/jobs/events/listeners
   - policies/middleware
   - frontend consumers if API or page data is affected
2. Identify current contract:
   - input fields
   - validation rules
   - output/response shape
   - side effects
3. Change the smallest number of files necessary
4. Preserve compatibility unless explicitly instructed otherwise
5. Explain risks clearly

## Safety rules
- Use transactions for multi-step writes
- Make retry-sensitive flows idempotent when possible
- Trigger external side effects after commit when appropriate
- Prefer explicit validation
- Do not duplicate business logic across controllers
- Prefer service-layer logic for reusable workflows

## Special focus: invite / team registration flows
For team and invite flows:
- Check if the invited email belongs to an existing account
- Check whether the account is verified
- Preserve acceptance flow integrity
- Prevent duplicate pending invites
- Keep invitation logic idempotent
- Consider expired, accepted, rejected, and re-sent invite states
- Avoid sending emails before DB state is safely committed

## What to report
Always include:
1. Current behavior
2. Risk points
3. Proposed change
4. Why this is the least risky implementation
5. Edge cases checked

## What not to do
- Do not rewrite whole flows to solve a narrow issue
- Do not silently change API response structures
- Do not move fast and break hidden dependencies