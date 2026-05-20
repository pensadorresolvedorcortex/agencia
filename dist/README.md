# Dist artifacts

Binary artifacts are **not committed** to this repository.

To generate the release package locally, run:

```bash
bash nafloresta-buy/bin/release-audit.sh
```

This command regenerates:

- `dist/nafloresta-buy-v1.0.0.zip`

The ZIP is intentionally ignored by Git for compatibility with binary-restricted workflows.
