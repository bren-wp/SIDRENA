<!--
Sidrena source file.
Author: Brendigo
Author URI: https://brendigo.com/
Plugin URI: https://brendigo.com/sidrene-cijene/
Support: sidrena@brendigo.com
-->

# Sidrena legal automation notes

The current Sidrena release line keeps retail daily generation scheduled before the operational 08:00 publication deadline, finalizes newly published service anchor prices only after submitted service metadata is saved, queues service cjenik regeneration after the final post-save lifecycle, preserves at least 30 days of public archive history, and keeps machine-readable publication safeguards enabled. Cross-version upgrade smoke tests also require the repair path to remain idempotent when migrating from an earlier Sidrena release of the same edition.

This engineering note is CI-visible context, not end-user legal advice.
