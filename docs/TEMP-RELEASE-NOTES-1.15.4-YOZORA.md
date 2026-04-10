# Shuriken Reviews 1.15.4 "Yozora"

Shuriken Reviews 1.15.4, codenamed Yozora, expands contextual analytics, improves mixed-scope reporting, and hardens frontend behavior for modern block-theme navigation.

## Highlights

- New Per-Post analytics workspace for ratings with contextual votes, including top-post charts, contextual average distribution, trending contexts, and sortable per-context tables.
- New context drill-down screen for a single rating on a single post/page/product, with summary cards, type-aware charts, and paginated vote history.
- New Global Votes vs. Per-Post Votes scope toggle on Item Stats pages so mixed-scope ratings can be analyzed without combining unrelated totals.
- Both rating blocks now support WordPress client-side navigation, and frontend widgets automatically re-initialize after Interactivity Router navigations.
- Ratings admin now shows mixed-scope badges such as `Global + 6 posts` when a rating has both global and contextual activity.
- Item Stats filter controls were reorganized into a more responsive unified filter bar.

## Improvements

- Parent rating breakdowns now respect the active scope and selected date range across stats, vote history, and charts.
- Global-only analytics queries now use scope-filtered totals, rolling averages, approval trends, and cumulative counts.
- Analytics and ratings-list displays received icon and formatting polish for stars and binary vote types.
- Public docs were updated for 1.15.4, including README, changelog, About tab copy, and REST API reference updates for contextual stats.

## Upgrade Notes

- Plugin version constants are now aligned to `1.15.4`.
- No new database migration is introduced in this release.

## Full Changelog

See `docs/CHANGELOG.md` for the full release history.