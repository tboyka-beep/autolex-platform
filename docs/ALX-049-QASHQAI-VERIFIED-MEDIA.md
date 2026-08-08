# ALX-049 — Nissan Qashqai verified media

## Scope

Add a verified Nissan Qashqai J12 vehicle image to the shared fail-closed media resolver. The mapping must never be reused for Qashqai J11 or another Nissan model.

## Verified source

- Vehicle: Nissan Qashqai (J12)
- File: `Nissan Qashqai (J12) IMG 4900.jpg`
- Source page: https://commons.wikimedia.org/wiki/File:Nissan_Qashqai_(J12)_IMG_4900.jpg
- Author: Alexander Migl
- Source declaration: own work
- License: CC BY-SA 4.0
- Production image: https://upload.wikimedia.org/wikipedia/commons/thumb/b/b6/Nissan_Qashqai_%28J12%29_IMG_4900.jpg/1280px-Nissan_Qashqai_%28J12%29_IMG_4900.jpg

## Fail-closed contract

- `Nissan + Qashqai` may use the verified J12 image when generation is absent.
- `Nissan + Qashqai + J12` must resolve to the verified J12 image.
- `Nissan + Qashqai + J11` must not resolve to J12 media.
- another Nissan model must not receive Qashqai media.
- both GitHub-hosted and Home Server production journeys must explicitly prove the Qashqai mapping is present on the live catalogue after deployment.
