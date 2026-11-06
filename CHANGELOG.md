# Checkpoints

Points the work has been marked as good, newest first. To look at or return to one: `git checkout <commit>`. For a live preview of exactly that version, put the full commit ID in place of `main` in the Playground link (see the README).

## Changes since Checkpoint 1

Not yet marked as a checkpoint.

- Job page: two columns matching the Jobs board, direct Apply button (website, or email with the job in the subject), the application email and subject shown after a click for browsers that cannot open mail, and a View All Listings button.
- Post a Job: no Preview step (button reads Submit Listing), a review confirmation with "within two business days" and a contact address, no "Have an account?" prompt, City / State / ZIP in Company Details, help text on the application field, required location fields, whole-dollar salary with a USD / CAD choice, and a Job category checklist plus an "Other" category.
- Jobs list: "Search Terms" box, a Category drawer, a Filter bar that collapses the sidebar on phones, and everything capped at 1080px.
- Bullets removed from the interface (job descriptions keep theirs) and the company name style fixed for themes that restyle it.
- Admin: Company Tagline, Video, X / Twitter and Position Filled hidden in the listing editor, and the Promote column hidden. Documented how to give Editors the Featured and Expiry fields.
- `tools/csv-to-wxr.py`: converts the old job board export into WordPress import files, with a report of data issues.

## Checkpoint 1: Jobs list and Post a Job form

Commit `703dd8373f03a7734f75445c58604386f0ec1ae8`

- Jobs list: sidebar filters with Keywords or city, State / Province, Job type drawer, Reset Filters and Sort by.
- City, State / Province and ZIP fields, state names shortened to codes, state and city search.
- Wesleyan brand colours and a consistent type scale.
- Post a Job form: wider, Church / Company wording, optional job description PDF, Remote Position under Job type, simplified company fields.
- Geolocation turned off.
