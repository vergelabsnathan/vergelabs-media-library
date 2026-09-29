# Tree lab results (2026-09-26)

tools/tree-lab.mjs on the box exports (shop 626, tech 200). Scores ignore names: pair F1, purity, real folders with 5+ pictures recovered, unfiled.

```
baseline run 1: 14 folders proposed · top: Lamps and décor, Doll clothing, Hiking boots, Wooden armchairs, Sunglasses, Kitchenware, Computers and keyboards, Cameras, Watches, Running shoes, Illustrations, Diagrams, Documents, Screenshots
baseline, filed by model   F1 leaf 31% (P 32% R 31%) · F1 top 20% · purity 50% · recovered 7/47 · leftover 66% · folders 14 · depth 1 · same names 0
baseline + rules (min 5)   F1 leaf 31% (P 32% R 31%) · F1 top 20% · purity 50% · recovered 7/47 · leftover 67% · folders 11 · depth 1 · same names 0
spent $0.15
baseline run 2: 19 folders proposed · top: Footwear, Furniture, Electronics, Jewellery, Audio equipment, Photography equipment, Accessories, Kitchenware, Timepiece, Eyewear, Computer peripheral, Beauty product, Wearable electronics, Luggage, Clothing, Kitchen appliance, Lighting, Musical instrument, Gaming peripheral
baseline, filed by model   F1 leaf 38% (P 27% R 67%) · F1 top 44% · purity 42% · recovered 8/47 · leftover 30% · folders 19 · depth 1 · same names 0
baseline + rules (min 5)   F1 leaf 38% (P 27% R 67%) · F1 top 44% · purity 42% · recovered 8/47 · leftover 30% · folders 19 · depth 1 · same names 0
spent $0.19
baseline run 3: 15 folders proposed · top: Footwear, Furniture, Electronics, Jewellery, Audio equipment, Photography equipment, Accessories, Kitchenware, Timepieces, Eyewear, Computer peripherals, Illustrations, Diagrams, Documents, Screenshots
baseline, filed by model   F1 leaf 31% (P 21% R 57%) · F1 top 41% · purity 34% · recovered 2/47 · leftover 37% · folders 15 · depth 1 · same names 0
baseline + rules (min 5)   F1 leaf 31% (P 21% R 57%) · F1 top 41% · purity 34% · recovered 2/47 · leftover 38% · folders 13 · depth 1 · same names 0
spent $0.18
<anonymous_script>:42
    {"name": "3d printers", "parent": ""}
```

```
== shop
runs 41,42,43,44,45: agreement with the others 77% 79% 78% 76% 73% -> keep run 42
  run 41                   F1 leaf 68% (P 66% R 70%) · F1 top 76% · purity 77% · recovered 38/47 · leftover 15% · folders 54 · depth 2 · same names 0
  run 42 (kept)            F1 leaf 70% (P 73% R 67%) · F1 top 62% · purity 83% · recovered 40/47 · leftover 24% · folders 50 · depth 2 · same names 0
  run 43                   F1 leaf 65% (P 65% R 66%) · F1 top 65% · purity 78% · recovered 34/47 · leftover 25% · folders 45 · depth 2 · same names 0
  run 44                   F1 leaf 63% (P 58% R 69%) · F1 top 51% · purity 72% · recovered 36/47 · leftover 13% · folders 51 · depth 2 · same names 0
  run 45                   F1 leaf 58% (P 52% R 65%) · F1 top 71% · purity 70% · recovered 31/47 · leftover 18% · folders 47 · depth 3 · same names 0
runs 51,52,53,54,55: agreement with the others 67% 69% 55% 70% 70% -> keep run 55
  run 51                   F1 leaf 57% (P 50% R 67%) · F1 top 51% · purity 69% · recovered 31/47 · leftover 14% · folders 53 · depth 3 · same names 0
  run 52                   F1 leaf 58% (P 51% R 67%) · F1 top 49% · purity 68% · recovered 30/47 · leftover 17% · folders 48 · depth 2 · same names 0
  run 53                   F1 leaf 42% (P 29% R 75%) · F1 top 68% · purity 46% · recovered 14/47 · leftover 15% · folders 31 · depth 2 · same names 0
  run 54                   F1 leaf 73% (P 73% R 73%) · F1 top 72% · purity 81% · recovered 42/47 · leftover 14% · folders 58 · depth 2 · same names 0
  run 55 (kept)            F1 leaf 69% (P 66% R 72%) · F1 top 76% · purity 77% · recovered 40/47 · leftover 12% · folders 55 · depth 2 · same names 0
consensus build 1          F1 leaf 70% (P 73% R 67%) · F1 top 62% · purity 83% · recovered 40/47 · leftover 24% · folders 50 · depth 2 · same names 0
consensus build 2          F1 leaf 69% (P 66% R 72%) · F1 top 76% · purity 77% · recovered 40/47 · leftover 12% · folders 55 · depth 2 · same names 0
build 1 vs build 2: 84% pair agreement · 277/626 pictures in the same folder
spent $0.51
== tech
runs 41,42,43,44,45: agreement with the others 85% 82% 87% 87% 82% -> keep run 43
  run 41                   F1 leaf 45% (P 48% R 42%) · F1 top 51% · purity 84% · recovered 9/11 · leftover 11% · belongs-nowhere placed 58/72 · folders 22 · depth 2 · same names 0
  run 42                   F1 leaf 44% (P 54% R 37%) · F1 top 50% · purity 85% · recovered 9/11 · leftover 11% · belongs-nowhere placed 58/72 · folders 24 · depth 2 · same names 0
  run 43 (kept)            F1 leaf 44% (P 47% R 41%) · F1 top 50% · purity 80% · recovered 9/11 · leftover 12% · belongs-nowhere placed 56/72 · folders 22 · depth 2 · same names 0
  run 44                   F1 leaf 44% (P 45% R 42%) · F1 top 52% · purity 78% · recovered 8/11 · leftover 11% · belongs-nowhere placed 56/72 · folders 20 · depth 2 · same names 0
  run 45                   F1 leaf 48% (P 54% R 42%) · F1 top 53% · purity 84% · recovered 9/11 · leftover 14% · belongs-nowhere placed 53/72 · folders 21 · depth 2 · same names 0
runs 51,52,53,54,55: agreement with the others 87% 83% 84% 82% 83% -> keep run 51
  run 51 (kept)            F1 leaf 42% (P 47% R 38%) · F1 top 53% · purity 80% · recovered 9/11 · leftover 14% · belongs-nowhere placed 53/72 · folders 21 · depth 2 · same names 0
  run 52                   F1 leaf 42% (P 45% R 39%) · F1 top 51% · purity 78% · recovered 9/11 · leftover 12% · belongs-nowhere placed 56/72 · folders 21 · depth 3 · same names 0
  run 53                   F1 leaf 46% (P 59% R 38%) · F1 top 44% · purity 88% · recovered 9/11 · leftover 19% · belongs-nowhere placed 48/72 · folders 22 · depth 2 · same names 0
  run 54                   F1 leaf 42% (P 45% R 40%) · F1 top 48% · purity 81% · recovered 8/11 · leftover 11% · belongs-nowhere placed 58/72 · folders 22 · depth 2 · same names 0
  run 55                   F1 leaf 48% (P 55% R 42%) · F1 top 49% · purity 85% · recovered 9/11 · leftover 16% · belongs-nowhere placed 49/72 · folders 21 · depth 2 · same names 0
consensus build 1          F1 leaf 44% (P 47% R 41%) · F1 top 50% · purity 80% · recovered 9/11 · leftover 12% · belongs-nowhere placed 56/72 · folders 22 · depth 2 · same names 0
consensus build 2          F1 leaf 42% (P 47% R 38%) · F1 top 53% · purity 80% · recovered 9/11 · leftover 14% · belongs-nowhere placed 53/72 · folders 21 · depth 2 · same names 0
build 1 vs build 2: 91% pair agreement · 101/200 pictures in the same folder
spent $0.22
[exited with code 0]
```

Reference taxonomy: shop 31/47, purity 62 %, 626/626 identical twice; tech 7/11, purity 68 %, 200/200 identical.

## 2026-09-27: the live service, the ceiling, and what closed the gap

`tools/tree-lab.mjs` on exports taken today (`box-filing-export.php` with `VGML_VECTORS=1`). Same score as above.

| | Shop recovered | Purity | Unfiled | Two builds agree |
|---|---|---|---|---|
| Old planner (above) | 2-8 / 47 | 34-50 % | 30-67 % | no |
| Live service, one real plan (5 runs, most-agreed) | 27 / 47 | 70 % | 31 % | - |
| Lab re-run today (5 runs, most-agreed) | 37 and 29 | 81 and 70 % | 20 % | 79 % |
| Votes across the five runs | 37 and 27 | 82 and 68 % | 20 % | 79 % |
| 5 runs, tightest kept, placed at 0.5 | 41 and 36 | 77 and 73 % | 9 and 6 % | 85 % |
| **15 runs, tightest kept, placed at 0.5** | **41 and 43** | **77 and 80 %** | **9 %** | **86 %** |
| Ceiling: each label in its best real folder | 47 | 99 % | 0 % | - |
| Ceiling with the broader class only | 27 | 68 % | 0 % | - |

Tech (200 labelled pictures, 72 of them belonging nowhere): 15 runs, tightest, placed at 0.5 gave 9/11 at 82 and 81 %, 4 and 6 % unfiled, 78 % agreement; 5 runs gave 9/11 at 82 %, 4 %, 89 %.

- The service's code is not the gap: the lab's own answers through its rules and choice score exactly as the lab does (37 and 29).
- Tightness -- the mean cosine of a picture to its folder's centre, from the site's vectors, no truth read -- ranks runs as their true scores do: shop 0.770 for 41/47, 0.729 for 26/47.
- Placement moves the unfiled share from 20-31 % to under 10 % at a small purity cost (81 -> 77 % on the kept shop tree).
- Pair F1 leaf of the two 15-run shop builds (replayed from the cache 2026-09-29, story 5): 71 and 75 %. The live service's kept trees scored 64-67 % on the same shop (story 5).
- `core/plan-tree.php` reproduces the lab's choice on the box: kept run 44 at 0.770, 41/47, 77 %, 9 %; 1.3 s and 67 MB for 626 pictures.
- Spent: about $1.70 of OpenRouter; 38 credits for the one live plan.
