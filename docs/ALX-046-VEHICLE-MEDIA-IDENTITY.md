# ALX-046 — Vehicle media identity

Goal: vehicle imagery must describe the same make/model (and generation when known) as the rendered vehicle data. Incorrect generic/stock substitutions are forbidden.

## Contract

- Resolve media by normalized `make + model`, with an optional generation-specific key taking precedence.
- If there is no verified mapping, render no replacement photo rather than a misleading one (fail closed).
- Every mapping carries a source URL and credit metadata.
- The public catalogue/model-card media layer uses one shared resolver.
- Existing card images are replaced only after an exact make/model match.
- Generic stock photography may remain only in slots that do not claim to depict a named vehicle.

## Initial verified mapping

- Opel Corsa: representative Opel Corsa F media from Wikimedia Commons, source page `File:Opel Corsa F IMG 5815.jpg`.

This mapping is intentionally narrow. Additional models are added only after the depicted vehicle identity is verified.
