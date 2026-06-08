# swetest output reference (ground truth for parsers)

> Captured from the compiled binary `/home/user/www/swisseph/swetests` (ephe at `./ephe`) on 2026-06-02.
> Every parser in the SP1–SP8 plans MUST be written against these exact shapes.
> **Common rules:** swetest prints the invoked command line as the FIRST stdout line (e.g. `./swetests -edir…`), and for `-local`/`-geopos` modes a `geo. long …, lat …, alt …` header line. Both go to **stdout** and MUST be skipped. Tabs and spaces are mixed → tokenize on `\s+`. `-` is a placeholder for an absent value. Position-pipeline modes use `-head` to suppress headers and `-gPPP` as the column separator (the literal string `PPP`).

---

## SP1 — Eclipses

### Solar, global (`-solecl -n2`) — 3 lines/event
```
annular solar	17.02.2026	  12:11:51.1	131.068478 km	0.9638/0.9797/0.9288	saros 121/61	2461089.008230
	  09:56:44.8    11:43:01.9    12:41:02.0    14:27:37.3 dt=71.1
	  87° 4' 6"	 -64°41' 2"	2 min 19.42 sec
total solar	12.08.2026	  17:45:56.8	-132.445818 km	1.0395/1.0178/1.0806	saros 126/48	2461265.240240
	  15:34:30.7    16:58:06.9    18:33:58.2    19:57:55.9 dt=71.3
	 -25° 5'40"	  65° 9'55"	2 min 18.03 sec
```
- L1: `<type> solar` · date(d.m.Y) · maxTime(UT) · coreShadowKm · `m1/m2/m3` · `saros <ser>/<num>` · JD
- L2: 4 contacts (exterior begin, total/annular begin, total/annular end, exterior end) · `dt=<deltaT>`
- L3: central-line lon · lat · maxDuration (`N min S.s sec`)

### Solar, local (`-solecl -local -geopos21.0,52.2,100 -n2`) — 2 lines/event (after geo header)
```
geo. long 21.000000, lat 52.200000, alt 100.000000
partial 12.08.2026	  18:03:06.0	0.8511/0.8511/0.8190	saros 126/48	2461265.252152
	0 min 0.00 sec	  17:14:40.3     -            -            -         dt=71.3
partial  2.08.2027	  09:22:21.3	0.4127/0.4127/0.3038	saros 136/38	2461619.890525
	0 min 0.00 sec	  08:26:19.3     -            -           10:19:20.8  dt=71.8
```
- L1: `<type>` · date · maxTime(UT) · `m1/m2/m3` · saros · JD  (NO coreShadowKm)
- L2: duration · 4 local contacts (`-` where not visible) · `dt=<deltaT>`

### Lunar, global (`-lunecl -n2`) — 3 lines/event
```
total lunar eclipse	 3.03.2026	  11:33:39.0	1.1507/2.1839	saros 133/27	2461102.981702
    08:44:21.8    09:50:02.9    11:04:30.1    12:02:49.3    13:17:15.5    14:23:05.3 dt=71.1
	-170°36'44"	   6°24' 6"
partial lunar eclipse	28.08.2026	  04:12:55.5	0.9299/1.9646	saros 138/29	2461280.675642
    01:23:55.2    02:33:50.3     -            -           05:52:00.3    07:01:47.1 dt=71.3
	 -63° 6'38"	  -9°18' 3"
```
- L1: `<type> lunar eclipse` · date · maxTime(UT) · `umbralMag/penumbralMag` · saros · JD
- L2: 6 contacts (penumbral begin, partial begin, total begin, total end, partial end, penumbral end) · `dt=<deltaT>` (`-` where absent)
- L3: sublunar lon · lat

---

## SP2 — Occultations (`-occult`)

### Global (`-occult -pf -xfAldebaran -n1`) — 3 lines/event
```
total   non-central 18.08.2033	  12:51:36.0	-3476.300002 km	1.000000	2463828.035833
	  12:06:05.0    12:06:05.0    13:36:53.3    13:36:53.3 dt=73.2
	 107° 7'	  72°35'
```
- L1: `<type>` (may include `non-central`/`central`) · date · maxTime(UT) · coreShadowKm · magnitude · JD
- L2: 4 contacts · `dt=<deltaT>`
- L3: lon · lat

### Local (`-occult … -local -geopos…`) — 2 lines/event after geo header
```
geo. long 21.000000, lat 52.200000, alt 100.000000
total            29.01.2034	  16:04:52.8	1.000000	2463992.170056
	52 min 21.03 sec	  15:39:02.2    15:39:02.2    16:31:23.2    16:31:23.2  dt=73.3
```
- L1: `<type>` · date · maxTime(UT) · magnitude · JD
- L2: duration · 4 local contacts · `dt=<deltaT>`

Note: occultation target is given via `-pf -xf<StarName>` (star) or `-p<planet>`.

---

## SP3 — Bodies & data extensions (position pipeline; `-head -gPPP`)

### Fixed star (`-pf -xfSirius -fPLBl`)
```
Sirius,alCMa   PPP 104°26'56.6685PPP -39°36'39.8652PPP 104.4490746
```
- Body name column carries the catalog name `Sirius,alCMa` (comma-joined common,designation). Otherwise identical to a normal position row.

### Asteroid by MPC number (`-ps -xs433 -fPLl`)
```
Eros           PPP  31°31'56.5890PPP 31.5323858
```
- Selector: `-ps -xs<MPCnumber>`. Name column = `Eros`.

### Output-only codes (pseudo-bodies)
```
-px  →  Sidereal Time  PPP01.01.2026 12:00:00 UT      (value is a time string, not a number)
-pq  →  Delta T        PPP 71.0013120                 (seconds)
-po  →  Ecl. Obl.      PPP 23.4381321                 (degrees; obliquity)
-pb  →  Ayanamsha      PPP 24.2218573  (with -sid1)   (degrees)
```
- These are body letters added to the `-p` selector. Name column is a fixed label; value column is the datum.

---

## SP4 — Batch ephemeris (`-n<count> -s<step>`)

`-n5 -s1` (5 daily frames in ONE process), `-fTPLl`:
```
01.01.2026 12:00:00 UTPPPSun            PPP 281° 4'40.9623PPP 281.0780451
01.01.2026 12:00:00 UTPPPMoon           PPP  74°13'32.2224PPP 74.2256173
02.01.2026 12:00:00 UTPPPSun            PPP 282° 5'48.8600PPP 282.0969055
…
```
- Each frame is prefixed by the `T` time column (`d.m.Y H:i:s UT`). N frames × M bodies rows.
- Step suffixes: bare=days, `m`=minutes, `mo`=months, `y`=years, `s`=seconds. NO `h`; use minutes (`-s360m`=6h). Sub-day works (`-s15m`).

---

## SP5 — Heliacal events (`-hev`)

`-hev -p3 -geopos21.0,52.2,100 -n1` (Venus) — one line per event, after geo header:
```
geo. long 21.000000, lat 52.200000, alt 100.000000
Venus heliacal rising : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min
Venus morning last    : 2027/03/28 03:52:26.5 UT (2461492.66142), opt 03:56:28.5, end 04:00:08.5, dur 7.7 min
Venus evening first   : 2027/11/10 15:23:38.9 UT (2461720.14142), opt 15:24:09.9, end 15:24:55.9, dur 1.3 min
Venus heliacal setting: 2028/05/23 18:56:52.6 UT (2461915.28950), opt 19:21:06.6, end 19:38:51.6, dur 42.0 min
```
- Format: `<body> <event type> : YYYY/MM/DD HH:MM:SS.s UT (JD), opt HH:MM:SS.s, end HH:MM:SS.s, dur N.n min`
- Event types: `heliacal rising`, `heliacal setting`, `morning last`, `evening first`.
- Target via `-p<planet>` or `-pf -xf<Star>`. Atmospheric/observer/optical models via `-at/-obs/-opt` (already implemented in RiseCommandBuilder).

---

## SP6 — Meridian transit (`-metr`)

`-metr -p0 -geopos21.0,52.2,100 -n2` (Sun) — one line per day, after geo header:
```
geo. long 21.000000, lat 52.200000, alt 100.000000
mtransit  1.01.2026	  10:39:32.3    itransit  1.01.2026	  22:39:46.3
mtransit  2.01.2026	  10:40:00.3    itransit  2.01.2026	  22:40:14.1
```
- Each line: `mtransit <date> <time>` (upper/southern meridian) + `itransit <date> <time>` (lower/northern meridian). Times UT.

---

## SP7 — Orbital elements (`-orbel`)

`-orbel -p4 -n1` (Mars) — header block + `key\tvalue` block per body:
```
date (dmy) 1.1.2026 greg.   0:00:00 TT		version 2.10.02
UT:  2461041.499178233     delta t: 71.000655 sec
TT:  2461041.500000000
Epsilon (t/m)     23°26'17.2935   23°26' 9.2285
Nutation           0° 0' 5.4212    0° 0' 8.0650
Mars             282°41'15.1684   -0°53'27.9922    2.410699395    0°45'58.9047
semiaxis         	1.523694
eccentricity     	0.093485
inclination      	1.847499
asc. node       	49.482984
arg. pericenter  	286.623510
pericenter       	336.106494
mean longitude   	291.928275
mean anomaly     	315.821781
ecc. anomaly     	311.830723
true anomaly     	307.703699
time pericenter  	2460438.822970  8.05.2024,  07:45:04.6
dist. pericenter 	1.381252
dist. apocenter  	1.666136
mean daily motion	0.524032
sid. period (y)  	1.880890
trop. period (y) 	1.880829
synodic cycle (d)	779.936372
```
- Parse the `key\tvalue` lines into a typed DTO (semiaxis, eccentricity, inclination, ascNode, argPericenter, pericenter, meanLongitude, meanAnomaly, eccAnomaly, trueAnomaly, timePericenter (JD + civil), distPericenter, distApocenter, meanDailyMotion, sidPeriodY, tropPeriodY, synodicCycleD). The header/Epsilon/Nutation/body-position lines are context, captured separately or skipped.

---

## SP8 — Relative ephemeris: differential (`-d`) & midpoint (`-D`)

Differential `-p2 -d0 -fPLl` (Mercury minus Sun), position pipeline:
```
Mer-SunPPP -11°39'46.5812PPP-11.6629392
```
Midpoint `-p6 -DD -fPLl` (Saturn/Chiron):
```
Sat/ChiPPP   9°23'53.0285PPP  9.3980635
```
- These are modifiers on the position pipeline: `-d<refBody>` (difference) or `-D<refBody>` (midpoint). The name column becomes `A-B` (differential) or `A/B` (midpoint). Columns otherwise follow `-fSEQ`.
