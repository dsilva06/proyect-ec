---
name: frontend-design
description: Safely redesign and improve frontend UI for proyect-ec without breaking existing functionality, endpoints, actions, or flows.
---

# Frontend Design Skill — proyect-ec

You are improving the frontend of `proyect-ec`.

Your job is not to make the UI merely prettier.
Your job is to improve clarity, hierarchy, spacing, responsiveness, and usability while preserving all existing functionality.

## Mission
When redesigning a page or component:
- Preserve all existing business actions
- Preserve all buttons, links, forms, handlers, endpoints, and bindings
- Preserve route/navigation logic
- Improve layout, structure, consistency, and visual hierarchy
- Avoid generic AI-looking design output

## Mandatory process
1. Inspect the relevant view/component/template and all connected JS/PHP/backend files that power it
2. Identify:
   - buttons/actions that must remain
   - forms/inputs that must remain
   - dynamic sections/states that must remain
   - endpoints/routes/events tied to the UI
3. Propose the minimum viable redesign that improves the page without breaking behavior
4. Implement incrementally
5. Verify no functionality was lost

## Hard constraints
- Do not remove endpoints
- Do not remove buttons unless explicitly instructed
- Do not remove form fields unless explicitly instructed
- Do not rename IDs/classes/props blindly if scripts may depend on them
- Do not replace real logic with placeholders
- Do not convert a production screen into a design mockup

## Visual standards
Prioritize:
- clear hierarchy
- cleaner spacing
- fewer crowded sections
- stronger typography structure
- consistent button treatments
- better grouping of related information
- responsive behavior
- useful empty/loading/error states

Avoid:
- random gradients
- overdesigned cards everywhere
- decorative clutter
- oversized hero sections that hurt productivity
- making dashboards look like landing pages

## Functional protection checklist
Before finishing, verify:
- every previous action still exists
- all links still point correctly
- forms still submit correctly
- validation messages still have space to render
- loading and disabled states are visible
- tables/lists/cards still render dynamic data correctly
- no hidden JS dependencies were broken

## Expected response format
For every UI task, provide:
1. What the current UI is doing
2. What is weak about it
3. What will be improved
4. What must not break
5. Files changed
6. Final implementation notes

## proyect-ec specific instruction
This project values working flows over visual experiments.
A redesign that breaks a flow is a bad redesign.