# Rubric Wizard Refactoring Guide

## Overview
The single-page Rubric Creation form has been refactored into a multi-step wizard with a vertical sidebar step indicator. This provides a better user experience by breaking down the form into manageable, focused steps.

## Architecture Changes

### 1. **Wizard State Management**
Added the following state variables to track wizard progress:

```typescript
const currentStep = ref(1);          // Current active step (1-3)
const totalSteps = 3;                // Total number of steps
const steps = [                      // Step definitions
  { id: 1, name: 'Rubric Title', description: 'Set up your rubric title' },
  { id: 2, name: 'Question', description: 'Enter the assignment question' },
  { id: 3, name: 'Rubric Matrix', description: 'Define levels and criteria' },
];
```

### 2. **Step Validation**
New `validateStep(step: number)` function validates each step before allowing progression:
- **Step 1**: Validates title is not empty
- **Step 2**: Validates question is not empty
- **Step 3**: Validates all rubric matrix data (levels, criteria, cell descriptions)

### 3. **Navigation Methods**

#### `nextStep()`
- Validates current step
- Advances to next step if validation passes
- Cannot go beyond Step 3

#### `prevStep()`
- Goes back one step
- No validation needed (always allowed)
- Cannot go before Step 1

#### `goToStep(step: number)`
- Allows direct navigation to previous steps
- Prevents jumping ahead (can only visit steps <= current step)
- Used by sidebar step indicators

### 4. **Layout Changes**

#### Two-Column Layout
```
┌─────────────────────────────────────────────┐
│  Navigation Bar (unchanged)                  │
├──────────────────────────────┬───────────────┤
│                              │               │
│   Main Content Area          │   Right       │
│   (Left 70%)                 │   Sidebar     │
│                              │   (30%)       │
│   - Step Header              │               │
│   - Progress Bar             │   • Step 1    │
│   - Form Step Content        │   • Step 2    │
│   - Navigation Buttons       │   • Step 3    │
│                              │               │
│                              │   Footer      │
└──────────────────────────────┴───────────────┘
```

#### Main Content Area (Left)
- **Step Header**: Shows step name and description
- **Progress Bar**: Visual indicator of completion (width = currentStep / totalSteps * 100%)
- **Content Sections**: Only the current step's form is rendered
  - Step 1: Title input field only
  - Step 2: Question textarea with AI Suggestion button
  - Step 3: Full Rubric Matrix table with Add Level/Criteria buttons
- **Step Navigation**: Back/Continue buttons at the bottom

#### Right Sidebar
- **Fixed Position**: Sticky on the right side, follows scroll
- **Brand Color**: Indigo gradient background (indigo-700 to indigo-900)
- **Header**: Shows "Rubric Setup" and current step (e.g., "Step 1 of 3")
- **Step List**: 
  - Numbered buttons (1, 2, 3) for each step
  - Active step: Highlighted with brighter color
  - Completed steps: Show checkmark icon
  - Future steps: Dimmed with opacity-50, cursor-not-allowed
  - Clickable: Can only click steps <= currentStep (go backward or to current)
- **Footer**: Help, Terms, Privacy links

### 5. **Form Behavior Changes**

#### Single Page → Multi-Step
- **Before**: All form fields visible at once
- **After**: Only current step's fields visible

#### Save Button Behavior
- On Steps 1-2: "Continue" button advances to next step
- On Step 3: "Save Draft" and "Publish" buttons available
  - "Save Draft" saves without publishing
  - "Publish" saves and publishes the rubric

#### Validation Flow
1. User fills out current step
2. Clicks "Continue" 
3. `validateStep()` runs
4. If valid: advance to next step
5. If invalid: show alert, stay on current step

### 6. **Step Content Breakdown**

#### Step 1: Rubric Title
```
┌──────────────────────────────────────┐
│ Rubric Title                         │
│ Set up your rubric title             │
│ [Progress Bar ▓░░ 33%]               │
│                                      │
│ ┌────────────────────────────────┐   │
│ │ Title Input                    │   │
│ │ "e.g. Narrative Essay..."      │   │
│ └────────────────────────────────┘   │
│ (Helper text)                        │
│                                      │
│             [Continue >]             │
└──────────────────────────────────────┘
```

#### Step 2: Question
```
┌──────────────────────────────────────┐
│ Assignment Question                  │
│ Enter the assignment question        │
│ [Progress Bar ▓▓░ 67%]               │
│                                      │
│ ┌────────────────────────────────┐   │
│ │ Question Textarea              │   │
│ │ (8 rows)                       │   │
│ └────────────────────────────────┘   │
│                                      │
│ [AI Suggest Criteria]                │
│                                      │
│ [< Back]              [Continue >]   │
└──────────────────────────────────────┘
```

#### Step 3: Rubric Matrix
```
┌──────────────────────────────────────┐
│ Rubric Matrix Setup                  │
│ Define levels and criteria           │
│ [Progress Bar ▓▓▓ 100%]              │
│                                      │
│ ┌────────────────────────────────┐   │
│ │ Rubric Matrix Table            │   │
│ │ [Add Level Button]             │   │
│ │ (Table with Levels, Criteria)  │   │
│ │ [Add Criteria Button]          │   │
│ └────────────────────────────────┘   │
│                                      │
│ Summary: X criteria rows             │
│ Max Score: Y pts                     │
│                                      │
│ [< Back] [Save Draft] [Publish]      │
└──────────────────────────────────────┘
```

### 7. **Styling & Animations**

#### Fade-in Animation
```css
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fade-in 0.3s ease-out; }
```
- Applies when step content changes
- Creates smooth visual transition between steps

#### Color Scheme
- **Active Step**: Indigo-600 background with white text
- **Completed Step**: Check icon in indigo-200
- **Future Steps**: Dimmed with opacity-50
- **Sidebar**: Indigo-700 to indigo-900 gradient

## Implementation Details

### Modified Files
- `resources/js/Pages/Demo/Index.vue`: Complete refactor

### New Imports Added
```typescript
import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
```

### State Variables Added
- `currentStep: ref(1)`
- `totalSteps: 3`
- `steps: Array`

### New Methods
- `validateStep(step: number): boolean`
- `nextStep(): void`
- `prevStep(): void`
- `goToStep(step: number): void`

### Modified Methods
- `saveRubric(publish: boolean)` - Now integrates with wizard state
- `form.reset()` - Now resets `currentStep` to 1

## User Flow

### Happy Path (Complete Rubric)
1. User sees Step 1: Title
2. Enters title → Clicks "Continue"
3. Validation passes → Proceeds to Step 2
4. Enters question → Clicks "Continue"
5. Validation passes → Proceeds to Step 3
6. Configures levels and criteria → Clicks "Publish"
7. Validation passes → Rubric saved and published
8. Success message displayed, form reset

### With Corrections
- User can click "Back" to return to previous steps
- Can click step number in sidebar to jump back
- Cannot skip ahead - must complete each step sequentially

### Using AI Suggestion
- On Step 2, click "AI Suggest Criteria" button
- Loads AI-generated criteria for Step 3
- Can edit before publishing

## Next Steps (Backend)

Once frontend is complete, the backend will need to:
1. No changes to `/assignments.store` endpoint (already handles full payload)
2. No changes to `/assignments.ai-rubric-suggestion` endpoint (already supports it)
3. Consider adding step-by-step draft saves if desired (optional feature)

## Benefits of This Refactor

✅ **Better UX**: Focused steps reduce cognitive load
✅ **Progress Visibility**: Users know where they are and what's next
✅ **Mobile-Friendly**: Could adapt sidebar to mobile hamburger menu
✅ **Extensible**: Easy to add new steps or reorder existing ones
✅ **Validation**: Step-by-step validation catches errors early
✅ **Navigation**: Flexible navigation (forward, backward, sidebar jumps)

## Testing Checklist

- [ ] Step 1: Can enter title and advance
- [ ] Step 2: Can enter question and advance
- [ ] Step 3: Can add levels, criteria, and publish
- [ ] Validation: Cannot skip step validation
- [ ] Navigation: Back button works
- [ ] Sidebar: Can click previous step numbers
- [ ] Sidebar: Cannot click future step numbers
- [ ] Progress Bar: Updates correctly
- [ ] AI Suggestion: Works on Step 2
- [ ] Save Draft: Works on Step 3
- [ ] Publish: Works on Step 3
- [ ] Form Reset: Clears all fields and resets to Step 1

