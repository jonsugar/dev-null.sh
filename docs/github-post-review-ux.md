# GitHub post review UX trial

The same pending GitHub posts and review controls appear in two places so they can be compared with live use:

- Settings: `/settings/github`, after repository and approval settings.
- Notifications: `/notifications`, above the regular notification feed.

Both surfaces render `resources/js/components/github-post-review-queue.tsx` and use the same post records and endpoints. Do not duplicate or migrate pending posts when selecting a preferred surface.

To remove the Settings review surface after the trial, remove its `GitHubPostReviewQueue` import and render from `resources/js/pages/github/settings.tsx`. Keep the GitHub page, since it also manages watched repositories and approval preferences.

To remove the Notifications review surface, remove its `GitHubPostReviewQueue` import and render from `resources/js/pages/notification/index.tsx`.

Keep the shared component while either surface uses it. If the Notifications surface is preferred, the repository and approval controls remain in Settings.

## Repository webhook setup

Add each repository in `/settings/github`, then open that repository's GitHub Settings → Webhooks → Add webhook. Copy its payload URL and unique secret from dev-null, select JSON, subscribe to the push event, and keep SSL verification enabled. Each repository gets its own URL and secret; dev-null stores the secret encrypted. A repository owner or admin must add the webhook.

The dev-null endpoint must be publicly reachable over HTTPS. For local testing, use a public tunnel and make sure its URL reaches the running dev-null app. The app uses the database queue connection to process webhook deliveries, so run a queue worker in each environment that receives GitHub webhooks.

Before removing a repository from dev-null settings, delete its webhook in GitHub. Otherwise GitHub continues sending deliveries to a URL dev-null no longer recognizes.
