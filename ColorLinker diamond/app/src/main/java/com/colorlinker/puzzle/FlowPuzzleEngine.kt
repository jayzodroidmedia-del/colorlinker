package com.colorlinker.puzzle

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.*
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.unit.dp
import java.util.Random
import kotlin.math.abs

data class FlowPoint(val row: Int, val col: Int)

data class FlowColorPair(
    val id: Int,
    val color: Color,
    val darkGlow: Color,
    val name: String,
    val p1: FlowPoint,
    val p2: FlowPoint, // Middle 2nd Dot
    val p3: FlowPoint, // Ending 3rd Dot
    val solution: List<FlowPoint>
) {
    val dots: List<FlowPoint> get() = listOf(p1, p2, p3)
}

data class FlowLevel(
    val level: Int,
    val gridSize: Int,
    val pairs: List<FlowColorPair>,
    val obstacles: List<FlowPoint> = emptyList(),
    val voidCells: Set<FlowPoint> = emptySet(),
    val shapeName: String = "Classic"
) {
    val blockedCells: List<FlowPoint> get() = obstacles
    val shapeVoids: Set<FlowPoint> get() = voidCells
}

enum class BoardShapeType(val displayName: String) {
    SQUARE("Classic"),
    CROSS("Plus Cross"),
    DONUT("Donut Ring"),
    DOUBLE_DONUT("Figure-8"),
    DIAMOND("Diamond"),
    SWISS_CHEESE("Swiss Lattice"),
    U_SHAPE("Horseshoe"),
    T_SHAPE("T-Corridor"),
    PINWHEEL("Pinwheel Maze"),
    HOURGLASS("Hourglass"),
    L_SHAPE("L-Corner"),
    STAIRCASE("Staircase")
}

object FlowPuzzleLevels {
    // 12 COMPLETELY DISTINCT, HIGH-CONTRAST COLORS (Zero Color Confusion)
    private val COLOR_PALETTE = listOf(
        Pair(Color(0xFFE50914), Color(0xFF800000)), // 1. Pure Bright Red
        Pair(Color(0xFF0066FF), Color(0xFF002288)), // 2. Royal Blue
        Pair(Color(0xFFFFEA00), Color(0xFF8A7A00)), // 3. Bright Yellow
        Pair(Color(0xFF00E676), Color(0xFF006020)), // 4. Emerald Green
        Pair(Color(0xFFFF6D00), Color(0xFF903000)), // 5. Tangerine Orange
        Pair(Color(0xFF00E5FF), Color(0xFF006080)), // 6. Electric Cyan
        Pair(Color(0xFFA855F7), Color(0xFF4C1D95)), // 7. Electric Purple
        Pair(Color(0xFFFF1493), Color(0xFF880044)), // 8. Neon Hot Magenta (Pink)
        Pair(Color(0xFFF1F5F9), Color(0xFF64748B)), // 9. Pure White Ice
        Pair(Color(0xFF8D6E63), Color(0xFF3E2723)), // 10. Chocolate Brown
        Pair(Color(0xFF76FF03), Color(0xFF336600)), // 11. Neon Lime
        Pair(Color(0xFF00B4D8), Color(0xFF004466))  // 12. Deep Ocean Blue
    )

    private val COLOR_NAMES = listOf(
        "Red", "Blue", "Yellow", "Green", "Orange", "Cyan",
        "Purple", "Pink", "White", "Brown", "Lime", "Ocean"
    )

    private fun getBoardShapeAndVoids(level: Int, gridSize: Int): Pair<BoardShapeType, Set<FlowPoint>> {
        if (level == 1) {
            return Pair(BoardShapeType.SQUARE, emptySet())
        }

        // Highly diverse mixed rotation schedule for maximum complexity and variation
        val shapePattern = listOf(
            BoardShapeType.SQUARE,        // L1 / L13: Classic Full Square
            BoardShapeType.CROSS,         // L2 / L14: Plus (+) Cross
            BoardShapeType.SQUARE,        // L3 / L15: Classic Full Square
            BoardShapeType.DONUT,         // L4 / L16: Donut Ring (Center Blank Void)
            BoardShapeType.DOUBLE_DONUT,  // L5 / L17: Figure-8 Double Void
            BoardShapeType.DIAMOND,       // L6 / L18: Diamond Octagon Cutout
            BoardShapeType.SQUARE,        // L7 / L19: Classic Full Square
            BoardShapeType.SWISS_CHEESE,  // L8 / L20: Swiss Lattice Multi-Hole
            BoardShapeType.U_SHAPE,       // L9 / L21: Horseshoe / U-Shape
            BoardShapeType.PINWHEEL,      // L10 / L22: Pinwheel Maze
            BoardShapeType.HOURGLASS,     // L11 / L23: Hourglass / Butterfly
            BoardShapeType.SQUARE,        // L12 / L24: Classic Full Square
            BoardShapeType.T_SHAPE,       // L13 / L25: T-Shape
            BoardShapeType.L_SHAPE,       // L14 / L26: L-Shape
            BoardShapeType.STAIRCASE      // L15 / L27: Stepped Staircase
        )

        val shapeType = shapePattern[(level - 1) % shapePattern.size]
        val voids = mutableSetOf<FlowPoint>()

        when (shapeType) {
            BoardShapeType.SQUARE -> {
                // No voids (Full playable grid)
            }
            BoardShapeType.CROSS -> {
                // Cut corners to make a Plus (+) Cross
                val cut = if (gridSize <= 6) 1 else 2
                for (r in 0 until cut) {
                    for (c in 0 until cut) {
                        voids.add(FlowPoint(r, c)) // Top-Left
                        voids.add(FlowPoint(r, gridSize - 1 - c)) // Top-Right
                        voids.add(FlowPoint(gridSize - 1 - r, c)) // Bottom-Left
                        voids.add(FlowPoint(gridSize - 1 - r, gridSize - 1 - c)) // Bottom-Right
                    }
                }
            }
            BoardShapeType.DONUT -> {
                // Hollow Center Hole (Donut Ring)
                if (gridSize >= 7) {
                    for (r in 2..(gridSize - 3)) {
                        for (c in 2..(gridSize - 3)) {
                            voids.add(FlowPoint(r, c))
                        }
                    }
                } else if (gridSize == 6) {
                    for (r in 2..3) {
                        for (c in 2..3) {
                            voids.add(FlowPoint(r, c))
                        }
                    }
                } else {
                    voids.add(FlowPoint(2, 2))
                }
            }
            BoardShapeType.DOUBLE_DONUT -> {
                // Figure-8: Two distinct center holes creating a central bridge corridor
                if (gridSize >= 7) {
                    val mid = gridSize / 2
                    voids.add(FlowPoint(2, mid))
                    voids.add(FlowPoint(gridSize - 3, mid))
                    if (gridSize >= 8) {
                        voids.add(FlowPoint(2, mid - 1))
                        voids.add(FlowPoint(gridSize - 3, mid - 1))
                    }
                } else {
                    voids.add(FlowPoint(1, 1))
                    voids.add(FlowPoint(gridSize - 2, gridSize - 2))
                }
            }
            BoardShapeType.DIAMOND -> {
                // Diamond / Octagon Cutout
                val mid = (gridSize - 1) / 2f
                val maxDist = if (gridSize <= 6) (gridSize - 1) * 0.72f else (gridSize - 1) * 0.70f
                for (r in 0 until gridSize) {
                    for (c in 0 until gridSize) {
                        if (abs(r - mid) + abs(c - mid) > maxDist) {
                            voids.add(FlowPoint(r, c))
                        }
                    }
                }
            }
            BoardShapeType.SWISS_CHEESE -> {
                // Multiple strategic 1x1 void holes creating intense maze choke points
                if (gridSize >= 6) {
                    voids.add(FlowPoint(1, 1))
                    voids.add(FlowPoint(1, gridSize - 2))
                    voids.add(FlowPoint(gridSize - 2, 1))
                    voids.add(FlowPoint(gridSize - 2, gridSize - 2))
                    if (gridSize >= 8) {
                        voids.add(FlowPoint(gridSize / 2, gridSize / 2))
                    }
                } else {
                    voids.add(FlowPoint(1, 1))
                    voids.add(FlowPoint(3, 3))
                }
            }
            BoardShapeType.U_SHAPE -> {
                // U / Horseshoe Shape (Void at top middle)
                val cutH = if (gridSize <= 6) 2 else 3
                val cutWStart = if (gridSize <= 6) 1 else 2
                val cutWEnd = gridSize - 1 - cutWStart
                if (cutWEnd >= cutWStart) {
                    for (r in 0 until cutH) {
                        for (c in cutWStart..cutWEnd) {
                            voids.add(FlowPoint(r, c))
                        }
                    }
                }
            }
            BoardShapeType.T_SHAPE -> {
                // T Shape (Bottom-Left and Bottom-Right cutouts)
                val topH = if (gridSize <= 6) 2 else 2
                val sideW = if (gridSize <= 6) 1 else 2
                for (r in topH until gridSize) {
                    for (c in 0 until sideW) {
                        voids.add(FlowPoint(r, c))
                    }
                    for (c in (gridSize - sideW) until gridSize) {
                        voids.add(FlowPoint(r, c))
                    }
                }
            }
            BoardShapeType.PINWHEEL -> {
                // Pinwheel Maze: 4 rotating perimeter notches
                val notch = if (gridSize <= 6) 1 else 2
                for (i in 0 until notch) {
                    voids.add(FlowPoint(0, i))
                    voids.add(FlowPoint(gridSize - 1 - i, 0))
                    voids.add(FlowPoint(gridSize - 1, gridSize - 1 - i))
                    voids.add(FlowPoint(i, gridSize - 1))
                }
            }
            BoardShapeType.HOURGLASS -> {
                // Hourglass / Butterfly (indented left and right mid-sections)
                val mid = gridSize / 2
                val cutDepth = if (gridSize <= 6) 1 else 2
                for (r in (mid - 1)..(mid + 1)) {
                    for (c in 0 until cutDepth) {
                        voids.add(FlowPoint(r, c))
                        voids.add(FlowPoint(r, gridSize - 1 - c))
                    }
                }
            }
            BoardShapeType.L_SHAPE -> {
                // L Shape (Top-Right quadrant is void)
                val cutSize = if (gridSize <= 6) 2 else 3
                for (r in 0 until cutSize) {
                    for (c in (gridSize - cutSize) until gridSize) {
                        voids.add(FlowPoint(r, c))
                    }
                }
            }
            BoardShapeType.STAIRCASE -> {
                // Staircase / Stepped Corners
                val cut = if (gridSize <= 6) 2 else 3
                for (r in 0 until cut) {
                    for (c in 0 until cut) {
                        if (r + c < cut) {
                            voids.add(FlowPoint(r, c)) // Top-Left step
                            voids.add(FlowPoint(gridSize - 1 - r, gridSize - 1 - c)) // Bottom-Right step
                        }
                    }
                }
            }
        }

        return Pair(shapeType, voids)
    }

    fun getLevel(level: Int): FlowLevel {
        val safeLevel = level.coerceIn(1, 11178)

        // Level 1: 3 Dots Per Color Tutorial Level (Classic 5x5 Square)
        if (safeLevel == 1) {
            return FlowLevel(
                level = 1,
                gridSize = 5,
                pairs = listOf(
                    FlowColorPair(
                        id = 1,
                        color = COLOR_PALETTE[1].first, // Blue
                        darkGlow = COLOR_PALETTE[1].second,
                        name = "Blue",
                        p1 = FlowPoint(0, 0),
                        p2 = FlowPoint(2, 0),
                        p3 = FlowPoint(4, 1),
                        solution = listOf(
                            FlowPoint(0, 0), FlowPoint(1, 0), FlowPoint(2, 0),
                            FlowPoint(3, 0), FlowPoint(4, 0), FlowPoint(4, 1)
                        )
                    ),
                    FlowColorPair(
                        id = 2,
                        color = COLOR_PALETTE[0].first, // Red
                        darkGlow = COLOR_PALETTE[0].second,
                        name = "Red",
                        p1 = FlowPoint(0, 3),
                        p2 = FlowPoint(0, 1),
                        p3 = FlowPoint(3, 1),
                        solution = listOf(
                            FlowPoint(0, 3), FlowPoint(0, 2), FlowPoint(0, 1),
                            FlowPoint(1, 1), FlowPoint(2, 1), FlowPoint(3, 1)
                        )
                    ),
                    FlowColorPair(
                        id = 3,
                        color = COLOR_PALETTE[2].first, // Yellow
                        darkGlow = COLOR_PALETTE[2].second,
                        name = "Yellow",
                        p1 = FlowPoint(1, 3),
                        p2 = FlowPoint(1, 2),
                        p3 = FlowPoint(2, 2),
                        solution = listOf(
                            FlowPoint(1, 3), FlowPoint(1, 2), FlowPoint(2, 2)
                        )
                    ),
                    FlowColorPair(
                        id = 4,
                        color = COLOR_PALETTE[4].first, // Orange
                        darkGlow = COLOR_PALETTE[4].second,
                        name = "Orange",
                        p1 = FlowPoint(0, 4),
                        p2 = FlowPoint(2, 4),
                        p3 = FlowPoint(3, 2),
                        solution = listOf(
                            FlowPoint(0, 4), FlowPoint(1, 4), FlowPoint(2, 4),
                            FlowPoint(2, 3), FlowPoint(3, 3), FlowPoint(3, 2)
                        )
                    ),
                    FlowColorPair(
                        id = 5,
                        color = COLOR_PALETTE[3].first, // Green
                        darkGlow = COLOR_PALETTE[3].second,
                        name = "Green",
                        p1 = FlowPoint(3, 4),
                        p2 = FlowPoint(4, 4),
                        p3 = FlowPoint(4, 2),
                        solution = listOf(
                            FlowPoint(3, 4), FlowPoint(4, 4), FlowPoint(4, 3), FlowPoint(4, 2)
                        )
                    )
                ),
                obstacles = emptyList(),
                voidCells = emptySet(),
                shapeName = "Classic"
            )
        }

        // MIXED DYNAMIC GRID SIZES (Non-linear, varied grid experience across levels)
        val gridSize = when {
            safeLevel <= 25 -> {
                val mixTable = listOf(
                    5,  // L1 (Square)
                    7,  // L2 (Cross +)
                    6,  // L3 (Square)
                    8,  // L4 (Donut Ring)
                    7,  // L5 (Figure-8)
                    7,  // L6 (Diamond)
                    8,  // L7 (Square)
                    6,  // L8 (Swiss Lattice)
                    7,  // L9 (U-Shape)
                    8,  // L10 (Pinwheel)
                    7,  // L11 (Hourglass)
                    9,  // L12 (Square)
                    7,  // L13 (T-Shape)
                    8,  // L14 (L-Shape)
                    6,  // L15 (Square)
                    8,  // L16 (Staircase)
                    9,  // L17 (Diamond)
                    7,  // L18 (Square)
                    8,  // L19 (U-Shape)
                    10, // L20 (Figure-8)
                    6,  // L21 (Square)
                    7,  // L22 (T-Shape)
                    8,  // L23 (Swiss Lattice)
                    9,  // L24 (Pinwheel)
                    7   // L25 (Square)
                )
                mixTable[(safeLevel - 1) % mixTable.size]
            }
            safeLevel <= 100 -> {
                val midMix = listOf(6, 8, 7, 9, 6, 10, 7, 8, 9, 6, 8, 7, 10, 9, 8, 6, 7, 9, 8, 10)
                midMix[(safeLevel - 1) % midMix.size]
            }
            else -> {
                val highMix = listOf(7, 9, 8, 10, 8, 9, 7, 10, 9, 8, 10, 7, 9, 10, 8, 9, 10, 8, 7, 10)
                highMix[(safeLevel - 1) % highMix.size]
            }
        }

        // Shape and Void Cells calculation
        val (shapeType, voidCells) = getBoardShapeAndVoids(safeLevel, gridSize)
        val playableCellsCount = (gridSize * gridSize) - voidCells.size

        // Obstacles (Blocked Cells / Rukawatein) Scaling per playable cells
        val numObstacles = when {
            safeLevel == 1 -> 0
            playableCellsCount < 22 -> 0
            gridSize <= 6 -> if (safeLevel % 3 == 0) 1 else 0
            gridSize == 7 -> if (voidCells.isEmpty()) (1 + safeLevel % 2) else (safeLevel % 2)
            gridSize == 8 -> if (voidCells.isEmpty()) (2 + safeLevel % 2) else 1
            else -> if (voidCells.isEmpty()) (2 + safeLevel % 3) else 2
        }

        // Denser, complex color count per grid
        val maxPossibleColors = (playableCellsCount / 4).coerceIn(4, minOf(COLOR_PALETTE.size, 10))
        val numColors = when {
            gridSize <= 5 -> 5
            gridSize == 6 -> minOf(6 + (safeLevel % 2), maxPossibleColors)
            gridSize == 7 -> minOf(7 + (safeLevel % 2), maxPossibleColors)
            gridSize == 8 -> minOf(8 + (safeLevel % 2), maxPossibleColors)
            else -> minOf(9 + (safeLevel % 3), maxPossibleColors)
        }.coerceIn(4, maxPossibleColors)

        // Deterministic generator with 3 dots per color, distinct palette, seed, snaky paths & obstacles
        val (pairs, obstacles) = generateSolvablePuzzle(safeLevel, gridSize, numColors, numObstacles, voidCells)
        return FlowLevel(
            level = safeLevel,
            gridSize = gridSize,
            pairs = pairs,
            obstacles = obstacles,
            voidCells = voidCells,
            shapeName = shapeType.displayName
        )
    }

    // Advanced 3-Dot Winding & Snaky Zig-Zag Puzzle Generator (Respects Void Shape Cutouts)
    private fun generateSolvablePuzzle(
        level: Int,
        gridSize: Int,
        numColors: Int,
        numObstacles: Int,
        voidCells: Set<FlowPoint>
    ): Pair<List<FlowColorPair>, List<FlowPoint>> {
        val rand = Random(level.toLong() * 999983L + 31337L)
        val allDirs = listOf(Pair(-1, 0), Pair(1, 0), Pair(0, -1), Pair(0, 1))

        var bestCandidate: Pair<List<FlowColorPair>, List<FlowPoint>>? = null
        var bestComplexityScore = -1

        for (attempt in 0 until 100) {
            val grid = Array(gridSize) { IntArray(gridSize) { -1 } } // -1 = empty, -2 = obstacle, -3 = void
            val paths = Array(numColors) { mutableListOf<FlowPoint>() }

            // Mark void cells
            for (pt in voidCells) {
                if (pt.row in 0 until gridSize && pt.col in 0 until gridSize) {
                    grid[pt.row][pt.col] = -3
                }
            }

            val playablePoints = mutableListOf<FlowPoint>()
            for (r in 0 until gridSize) {
                for (c in 0 until gridSize) {
                    val pt = FlowPoint(r, c)
                    if (!voidCells.contains(pt)) {
                        playablePoints.add(pt)
                    }
                }
            }
            playablePoints.shuffle(rand)

            // 1. Select non-clustering obstacle positions from playable points
            val obstacles = mutableListOf<FlowPoint>()
            if (numObstacles > 0 && playablePoints.size >= 24) {
                for (pt in playablePoints) {
                    if (obstacles.size >= numObstacles) break
                    val hasAdjacent = obstacles.any { abs(it.row - pt.row) + abs(it.col - pt.col) <= 1 }
                    if (!hasAdjacent) {
                        obstacles.add(pt)
                        grid[pt.row][pt.col] = -2
                    }
                }
            }

            // 2. Spread seeds across available cells
            val available = playablePoints.filter { !obstacles.contains(it) }.toMutableList()
            if (available.size < numColors * 3) continue

            val seedPoints = available.take(numColors)
            for (i in 0 until numColors) {
                val pt = seedPoints[i]
                grid[pt.row][pt.col] = i
                paths[i].add(pt)
            }

            // 3. Grow paths using Winding & Zig-Zag Perpendicular Steering (95% snaking probability)
            var changed = true
            var iterations = 0
            while (changed && iterations < 600) {
                changed = false
                iterations++
                val order = (0 until numColors).shuffled(rand)

                for (colorIdx in order) {
                    val path = paths[colorIdx]
                    if (path.isEmpty()) continue

                    val fromEnd = rand.nextBoolean()
                    val cur = if (fromEnd) path.last() else path.first()
                    val prev = if (path.size > 1) {
                        if (fromEnd) path[path.size - 2] else path[1]
                    } else null

                    val prioritizedDirs = if (prev != null) {
                        val dRow = cur.row - prev.row
                        val dCol = cur.col - prev.col
                        val perp1 = Pair(-dCol, dRow)
                        val perp2 = Pair(dCol, -dRow)
                        val straight = Pair(dRow, dCol)
                        val perps = listOf(perp1, perp2).shuffled(rand)

                        // 95% High probability of perpendicular steering for complex winding snaky mazes
                        if (rand.nextFloat() < 0.95f) {
                            perps + listOf(straight)
                        } else {
                            listOf(straight) + perps
                        }
                    } else {
                        allDirs.shuffled(rand)
                    }

                    for (d in prioritizedDirs) {
                        val nr = cur.row + d.first
                        val nc = cur.col + d.second

                        if (nr in 0 until gridSize && nc in 0 until gridSize && grid[nr][nc] == -1) {
                            var adjacentCount = 0
                            for (cd in allDirs) {
                                val ar = nr + cd.first
                                val ac = nc + cd.second
                                if (ar in 0 until gridSize && ac in 0 until gridSize && grid[ar][ac] == colorIdx) {
                                    adjacentCount++
                                }
                            }

                            if (adjacentCount <= 1) {
                                grid[nr][nc] = colorIdx
                                val newPt = FlowPoint(nr, nc)
                                if (fromEnd) {
                                    path.add(newPt)
                                } else {
                                    path.add(0, newPt)
                                }
                                changed = true
                                break
                            }
                        }
                    }
                }
            }

            // 4. Attach unassigned empty cells
            for (r in 0 until gridSize) {
                for (c in 0 until gridSize) {
                    if (grid[r][c] == -1) {
                        val pt = FlowPoint(r, c)
                        for (d in allDirs) {
                            val nr = r + d.first
                            val nc = c + d.second
                            if (nr in 0 until gridSize && nc in 0 until gridSize && grid[nr][nc] >= 0) {
                                val colorIdx = grid[nr][nc]
                                val path = paths[colorIdx]
                                if (path.last() == FlowPoint(nr, nc)) {
                                    grid[r][c] = colorIdx
                                    path.add(pt)
                                    break
                                } else if (path.first() == FlowPoint(nr, nc)) {
                                    grid[r][c] = colorIdx
                                    path.add(0, pt)
                                    break
                                }
                            }
                        }
                    }
                }
            }

            // 5. Evaluate: Check length >= 3 for all paths so 3 distinct dots exist
            val allValid = paths.all { it.size >= 3 }
            val filledCells = paths.sumOf { it.size }
            val targetFill = (playablePoints.size - obstacles.size) * 0.75

            if (allValid && filledCells >= targetFill) {
                var totalTurns = 0
                for (path in paths) {
                    for (i in 1 until path.size - 1) {
                        val dr1 = path[i].row - path[i - 1].row
                        val dc1 = path[i].col - path[i - 1].col
                        val dr2 = path[i + 1].row - path[i].row
                        val dc2 = path[i + 1].col - path[i].col
                        if (dr1 != dr2 || dc1 != dc2) {
                            totalTurns++
                        }
                    }
                }

                // Dot dispersion score (further apart dots = harder puzzle)
                var dotDispersion = 0
                for (path in paths) {
                    val p1 = path.first()
                    val p2 = path[path.size / 2]
                    val p3 = path.last()
                    dotDispersion += abs(p1.row - p2.row) + abs(p1.col - p2.col) +
                                     abs(p2.row - p3.row) + abs(p2.col - p3.col)
                }

                val complexityScore = (totalTurns * 4) + (filledCells * 3) + (dotDispersion * 2)

                val pairs = paths.mapIndexed { idx, path ->
                    val colorPair = COLOR_PALETTE[idx]
                    val colorName = COLOR_NAMES[idx]
                    val midIdx = path.size / 2
                    FlowColorPair(
                        id = idx + 1,
                        color = colorPair.first,
                        darkGlow = colorPair.second,
                        name = colorName,
                        p1 = path.first(),
                        p2 = path[midIdx], // 2nd Dot (Middle)
                        p3 = path.last(),  // 3rd Dot (End)
                        solution = path
                    )
                }

                if (complexityScore > bestComplexityScore) {
                    bestComplexityScore = complexityScore
                    bestCandidate = Pair(pairs, obstacles)
                }
            }
        }

        if (bestCandidate != null) {
            return bestCandidate
        }

        // Fallback with unique 3 dots per color respecting voids and obstacles
        return Pair(generateFallbackSnakePuzzle(level, gridSize, numColors, voidCells, emptyList()), emptyList())
    }

    private fun generateFallbackSnakePuzzle(
        level: Int,
        gridSize: Int,
        numColors: Int,
        voidCells: Set<FlowPoint>,
        obstacles: List<FlowPoint>
    ): List<FlowColorPair> {
        val result = mutableListOf<FlowColorPair>()
        val playable = mutableListOf<FlowPoint>()
        for (r in 0 until gridSize) {
            val cols = if (r % 2 == 0) (0 until gridSize) else (gridSize - 1 downTo 0)
            for (c in cols) {
                val pt = FlowPoint(r, c)
                if (!voidCells.contains(pt) && !obstacles.contains(pt)) {
                    playable.add(pt)
                }
            }
        }

        if (playable.isEmpty()) return emptyList()

        val paths = Array(numColors) { mutableListOf<FlowPoint>() }
        val perColor = maxOf(3, playable.size / numColors)

        var colorIdx = 0
        for (pt in playable) {
            paths[colorIdx].add(pt)
            if (paths[colorIdx].size >= perColor && colorIdx < numColors - 1) {
                colorIdx++
            }
        }

        for (i in 0 until numColors) {
            val path = paths[i]
            if (path.isNotEmpty()) {
                val colorPair = COLOR_PALETTE[i % COLOR_PALETTE.size]
                val colorName = COLOR_NAMES[i % COLOR_NAMES.size]
                val midIdx = if (path.size >= 3) path.size / 2 else 0
                result.add(
                    FlowColorPair(
                        id = i + 1,
                        color = colorPair.first,
                        darkGlow = colorPair.second,
                        name = colorName,
                        p1 = path.first(),
                        p2 = path[midIdx],
                        p3 = path.last(),
                        solution = path
                    )
                )
            }
        }

        return result
    }
}

@Composable
fun FlowGameBoard(
    level: Int,
    restartTrigger: Int,
    hintTrigger: Int,
    onMoveMade: () -> Unit,
    onWin: () -> Unit = {},
    onWinWithSolution: (Map<Int, List<FlowPoint>>) -> Unit = {},
    onLifeLost: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    val currentLevelData = remember(level) {
        FlowPuzzleLevels.getLevel(level)
    }

    val gridSize = currentLevelData.gridSize
    val pairs = currentLevelData.pairs
    val obstacles = currentLevelData.obstacles
    val voidCells = currentLevelData.voidCells

    // State of paths drawn for each color id
    val paths = remember(level, restartTrigger) {
        mutableStateMapOf<Int, List<FlowPoint>>()
    }

    var currentDrawingPairId by remember(level, restartTrigger) {
        mutableStateOf<Int?>(null)
    }

    var isWon by remember(level, restartTrigger) {
        mutableStateOf(false)
    }

    // Dynamic Spacing and Corner radius based on Grid Size for optimal fit
    val spacingDp = when {
        gridSize <= 6 -> 6.dp
        gridSize <= 8 -> 4.dp
        else -> 3.dp
    }
    val cornerDp = when {
        gridSize <= 6 -> 8.dp
        gridSize <= 8 -> 6.dp
        else -> 4.dp
    }
    val spacingPx = with(LocalDensity.current) { spacingDp.toPx() }

    // Fully connected if all 3 dots of that color are present in the path and starts/ends on dots
    fun isPairFullyConnected(pairId: Int, path: List<FlowPoint>): Boolean {
        val pair = pairs.find { it.id == pairId } ?: return false
        if (path.size < 3) return false
        val containsAllDots = pair.dots.all { path.contains(it) }
        val startsOnDot = pair.dots.contains(path.first())
        val endsOnDot = pair.dots.contains(path.last())
        val distinctEnds = path.first() != path.last()
        return containsAllDots && startsOnDot && endsOnDot && distinctEnds
    }

    // Win check function: checks if all pairs are connected
    fun checkWinCondition() {
        if (isWon) return
        var allConnected = true
        for (pair in pairs) {
            val p = paths[pair.id] ?: emptyList()
            if (!isPairFullyConnected(pair.id, p)) {
                allConnected = false
                break
            }
        }

        if (allConnected && !isWon) {
            isWon = true
            SoundManager.playLevelWinSound()
            onWin()
            onWinWithSolution(paths.toMap())
        }
    }

    // Handle Hint Trigger
    LaunchedEffect(hintTrigger) {
        if (hintTrigger > 0 && !isWon) {
            val unconnected = pairs.firstOrNull { pair ->
                val p = paths[pair.id] ?: emptyList()
                !isPairFullyConnected(pair.id, p)
            }
            if (unconnected != null) {
                // Clear any other paths that intersect with the solution path
                for (solCell in unconnected.solution) {
                    for ((id, path) in paths.entries.toList()) {
                        if (id != unconnected.id && path.contains(solCell)) {
                            val cutIdx = path.indexOf(solCell)
                            paths[id] = path.take(cutIdx)
                        }
                    }
                }
                paths[unconnected.id] = unconnected.solution
                SoundManager.playPipeConnectSound()
                onMoveMade()
                checkWinCondition()
            }
        }
    }

    Box(
        modifier = modifier
            .fillMaxSize()
            .pointerInput(level, restartTrigger, isWon) {
                if (isWon) return@pointerInput

                detectDragGestures(
                    onDragStart = { offset ->
                        val totalW = size.width
                        val totalH = size.height
                        val cellW = (totalW - (gridSize - 1) * spacingPx) / gridSize
                        val cellH = (totalH - (gridSize - 1) * spacingPx) / gridSize
                        val stepX = cellW + spacingPx
                        val stepY = cellH + spacingPx

                        val col = (offset.x / stepX).toInt().coerceIn(0, gridSize - 1)
                        val row = (offset.y / stepY).toInt().coerceIn(0, gridSize - 1)
                        val touchedPoint = FlowPoint(row, col)

                        // Cannot start on obstacle or void blank cell
                        if (obstacles.contains(touchedPoint) || voidCells.contains(touchedPoint)) return@detectDragGestures

                        // Check if touched any of the 3 dots of any color
                        val touchedPair = pairs.find { it.dots.contains(touchedPoint) }
                        if (touchedPair != null) {
                            currentDrawingPairId = touchedPair.id
                            paths[touchedPair.id] = listOf(touchedPoint)
                        } else {
                            // Check if touched existing path of a color
                            for ((id, path) in paths) {
                                val idx = path.indexOf(touchedPoint)
                                if (idx != -1) {
                                    currentDrawingPairId = id
                                    paths[id] = path.take(idx + 1)
                                    break
                                }
                            }
                        }
                    },
                    onDrag = { change, _ ->
                        change.consume()
                        val activeId = currentDrawingPairId ?: return@detectDragGestures
                        val pair = pairs.find { it.id == activeId } ?: return@detectDragGestures
                        var currentPath = paths[activeId] ?: return@detectDragGestures
                        if (currentPath.isEmpty()) return@detectDragGestures

                        val totalW = size.width
                        val totalH = size.height
                        val cellW = (totalW - (gridSize - 1) * spacingPx) / gridSize
                        val cellH = (totalH - (gridSize - 1) * spacingPx) / gridSize
                        val stepX = cellW + spacingPx
                        val stepY = cellH + spacingPx

                        val targetCol = (change.position.x / stepX).toInt().coerceIn(0, gridSize - 1)
                        val targetRow = (change.position.y / stepY).toInt().coerceIn(0, gridSize - 1)
                        val curCell = FlowPoint(targetRow, targetCol)

                        // 1. Direct Backtrack Check (if finger dragged back onto existing segment of this path)
                        val backIdx = currentPath.indexOf(curCell)
                        if (backIdx != -1 && backIdx < currentPath.size - 1) {
                            currentPath = currentPath.take(backIdx + 1)
                            paths[activeId] = currentPath
                            return@detectDragGestures
                        }

                        // 2. Strict 4-Way Orthogonal Step-by-Step Movement (Left, Right, Up, Down only)
                        var currR = currentPath.last().row
                        var currC = currentPath.last().col

                        while (currR != targetRow || currC != targetCol) {
                            val dr = targetRow - currR
                            val dc = targetCol - currC

                            // Determine next 1-step orthogonal direction (Left, Right, Up, or Down)
                            if (abs(dr) >= abs(dc) && dr != 0) {
                                currR += if (dr > 0) 1 else -1
                            } else if (dc != 0) {
                                currC += if (dc > 0) 1 else -1
                            } else if (dr != 0) {
                                currR += if (dr > 0) 1 else -1
                            } else {
                                break
                            }

                            val stepCell = FlowPoint(currR, currC)

                            // Rule 1: Cannot move into obstacles or void blank cells
                            if (obstacles.contains(stepCell) || voidCells.contains(stepCell)) {
                                break
                            }

                            // Rule 2: Cannot step on another color's dots
                            val isOtherColorDot = pairs.any { it.id != activeId && it.dots.contains(stepCell) }
                            if (isOtherColorDot) {
                                break
                            }

                            // Rule 3: If stepping onto its own path, backtrack to that position
                            val stepBackIdx = currentPath.indexOf(stepCell)
                            if (stepBackIdx != -1) {
                                currentPath = currentPath.take(stepBackIdx + 1)
                                paths[activeId] = currentPath
                                continue
                            }

                            // Rule 4: If current path is already fully connected (all dots visited), cannot extend further
                            if (isPairFullyConnected(activeId, currentPath)) {
                                break
                            }

                            // Rule 5: Disconnect other color pipe if crossing it
                            for ((otherId, otherPath) in paths.entries.toList()) {
                                if (otherId != activeId && otherPath.contains(stepCell)) {
                                    val wasConnected = isPairFullyConnected(otherId, otherPath)
                                    val cutIdx = otherPath.indexOf(stepCell)
                                    paths[otherId] = otherPath.take(cutIdx)
                                    SoundManager.playPipeBreakSound()
                                    if (wasConnected) {
                                        onLifeLost()
                                    }
                                }
                            }

                            // Append strictly orthogonal step (Left, Right, Up, Down)
                            val isHittingNewDot = pair.dots.contains(stepCell) && !currentPath.contains(stepCell)
                            currentPath = currentPath + stepCell
                            paths[activeId] = currentPath

                            if (isHittingNewDot || isPairFullyConnected(activeId, currentPath)) {
                                SoundManager.playPipeConnectSound()
                            }
                            checkWinCondition()

                            // If connected to the final target dot, stop stepping further
                            if (isPairFullyConnected(activeId, currentPath)) {
                                break
                            }
                        }
                    },
                    onDragEnd = {
                        currentDrawingPairId = null
                        onMoveMade()
                        checkWinCondition()
                    },
                    onDragCancel = {
                        currentDrawingPairId = null
                    }
                )
            },
        contentAlignment = Alignment.Center
    ) {
        // 1. GRID BOXES (With Obstacles & Blank Void Cutouts Support)
        Column(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(spacingDp, Alignment.CenterVertically),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            for (row in 0 until gridSize) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(spacingDp, Alignment.CenterHorizontally),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    for (col in 0 until gridSize) {
                        val pt = FlowPoint(row, col)
                        val isVoid = voidCells.contains(pt)
                        val isObstacle = obstacles.contains(pt)

                        if (isVoid) {
                            // Blank Void: Transparent spacer (no grid cell box or border)
                            Spacer(
                                modifier = Modifier
                                    .weight(1f)
                                    .aspectRatio(1f)
                            )
                        } else {
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .aspectRatio(1f)
                                    .background(
                                        color = if (isObstacle) Color(0xFF1E2028).copy(alpha = 0.88f) else Color.White.copy(alpha = 0.12f),
                                        shape = RoundedCornerShape(cornerDp)
                                    )
                                    .border(
                                        width = if (isObstacle) 1.5.dp else 1.dp,
                                        color = if (isObstacle) Color(0xFFFF5252).copy(alpha = 0.65f) else Color.White.copy(alpha = 0.25f),
                                        shape = RoundedCornerShape(cornerDp)
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                if (isObstacle) {
                                    Canvas(
                                        modifier = Modifier
                                            .fillMaxSize()
                                            .padding(if (gridSize <= 6) 7.dp else 4.dp)
                                    ) {
                                        val strokeW = if (gridSize <= 6) 2.5.dp.toPx() else 1.8.dp.toPx()
                                        val obstacleColor = Color(0xFFFF5252).copy(alpha = 0.75f)
                                        drawLine(
                                            color = obstacleColor,
                                            start = Offset(0f, 0f),
                                            end = Offset(size.width, size.height),
                                            strokeWidth = strokeW,
                                            cap = StrokeCap.Round
                                        )
                                        drawLine(
                                            color = obstacleColor,
                                            start = Offset(size.width, 0f),
                                            end = Offset(0f, size.height),
                                            strokeWidth = strokeW,
                                            cap = StrokeCap.Round
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. CANVAS FOR PIPES AND VIBRANT CANDY GEMS (EXACTLY 3 DOTS PER UNIQUE COLOR)
        Canvas(modifier = Modifier.fillMaxSize()) {
            val totalW = size.width
            val totalH = size.height
            val cellW = (totalW - (gridSize - 1) * spacingPx) / gridSize
            val cellH = (totalH - (gridSize - 1) * spacingPx) / gridSize
            val stepX = cellW + spacingPx
            val stepY = cellH + spacingPx

            val pipeStroke = cellW * 0.44f
            val dotRadius = cellW * 0.35f

            fun getCenter(r: Int, c: Int): Offset {
                val cx = c * stepX + cellW / 2f
                val cy = r * stepY + cellH / 2f
                return Offset(cx, cy)
            }

            // Draw Pipes
            for (pair in pairs) {
                val path = paths[pair.id] ?: continue
                if (path.size > 1) {
                    val composePath = Path()
                    val firstCenter = getCenter(path[0].row, path[0].col)
                    composePath.moveTo(firstCenter.x, firstCenter.y)

                    for (i in 1 until path.size) {
                        val nextCenter = getCenter(path[i].row, path[i].col)
                        composePath.lineTo(nextCenter.x, nextCenter.y)
                    }

                    drawPath(
                        path = composePath,
                        color = pair.color,
                        style = Stroke(
                            width = pipeStroke,
                            cap = StrokeCap.Round,
                            join = StrokeJoin.Round
                        )
                    )
                }
            }

            // Draw Vibrant Candy Neon Gem Dots (Exactly 3 Dots per Unique Color)
            for (pair in pairs) {
                for (dot in pair.dots) {
                    val center = getCenter(dot.row, dot.col)

                    // 1. Outer Soft Colored Neon Glow Aura
                    drawCircle(
                        color = pair.color.copy(alpha = 0.40f),
                        radius = dotRadius * 1.28f,
                        center = center
                    )

                    // 2. Outer Accent Border Ring (Crisp & Vibrant)
                    drawCircle(
                        color = Color.White.copy(alpha = 0.92f),
                        radius = dotRadius * 1.05f,
                        center = center,
                        style = Stroke(width = if (gridSize <= 6) 2.5.dp.toPx() else 1.8.dp.toPx())
                    )

                    // 3. Vibrant Solid Candy Sphere (Radial Gradient)
                    drawCircle(
                        brush = Brush.radialGradient(
                            colors = listOf(
                                Color.White.copy(alpha = 0.65f),
                                pair.color,
                                pair.darkGlow
                            ),
                            center = Offset(center.x - dotRadius * 0.25f, center.y - dotRadius * 0.30f),
                            radius = dotRadius * 1.15f
                        ),
                        radius = dotRadius,
                        center = center
                    )

                    // 4. Glossy Specular Glass Arc Highlight (Top Dome Reflection)
                    drawOval(
                        color = Color.White.copy(alpha = 0.70f),
                        topLeft = Offset(center.x - dotRadius * 0.50f, center.y - dotRadius * 0.70f),
                        size = Size(dotRadius * 1.0f, dotRadius * 0.50f)
                    )

                    // 5. Inner Crisp Target Ring (Arcade Jewel Center)
                    drawCircle(
                        color = Color.White.copy(alpha = 0.85f),
                        radius = dotRadius * 0.28f,
                        center = center,
                        style = Stroke(width = 1.5.dp.toPx())
                    )

                    // 6. Center Sparkle Dot
                    drawCircle(
                        color = Color.White,
                        radius = dotRadius * 0.12f,
                        center = center
                    )
                }
            }
        }
    }
}
