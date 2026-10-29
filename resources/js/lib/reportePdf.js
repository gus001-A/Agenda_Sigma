import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { fmtDL } from '@/lib/util';

/**
 * Exporta el reporte del periodo a PDF: indicadores a lo ancho de la página, cada gráfica (tomada del
 * lienzo en pantalla) con su tabla de datos. `graficas` es una lista de
 * { titulo, canvas, lado?, tabla: { cabeza, filas } }; con `lado` la gráfica va a la izquierda y la tabla a la derecha.
 */
const NAVY = [23, 58, 95];
const TEAL = [31, 158, 134];
const GRIS = [100, 116, 139];

/** Copia el lienzo de Chart.js sobre fondo blanco (es transparente) y lo devuelve como JPEG. */
function imagen(canvas) {
    const c = document.createElement('canvas');
    c.width = canvas.width;
    c.height = canvas.height;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(canvas, 0, 0);
    return c.toDataURL('image/jpeg', 0.85);
}

export function exportarReportePdf({ clinica, usuario, desde, hasta, kpis, graficas }) {
    const doc = new jsPDF({ unit: 'pt', format: 'letter', compress: true });
    const W = doc.internal.pageSize.getWidth();
    const H = doc.internal.pageSize.getHeight();
    const M = 36;
    const ancho = W - M * 2;
    let y = 0;

    const saltoSi = (alto) => {
        if (y + alto > H - 40) {
            doc.addPage();
            y = M;
        }
    };
    const titulo = (texto) => {
        saltoSi(40);
        doc.setFillColor(238, 244, 250);
        doc.roundedRect(M, y, ancho, 24, 4, 4, 'F');
        doc.setFillColor(...TEAL);
        doc.rect(M, y, 4, 24, 'F');
        doc.setTextColor(...NAVY).setFont('helvetica', 'bold').setFontSize(12).text(texto, M + 14, y + 16);
        y += 34;
    };
    const tabla = (t, x, w, inicio) => {
        autoTable(doc, {
            startY: inicio,
            head: [t.cabeza],
            body: t.filas,
            theme: 'striped',
            styles: { fontSize: 9, cellPadding: 4, textColor: [30, 41, 59] },
            headStyles: { fillColor: TEAL, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [244, 248, 252] },
            columnStyles: Object.fromEntries(t.cabeza.map((_, i) => [i, i ? { halign: 'right' } : { fontStyle: 'bold' }])),
            margin: { left: x, right: W - x - w },
            tableWidth: w,
        });
        return doc.lastAutoTable.finalY;
    };

    // ---------- Encabezado ----------
    doc.setFillColor(...NAVY);
    doc.rect(0, 0, W, 78, 'F');
    doc.setFillColor(...TEAL);
    doc.rect(0, 78, W, 4, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold').setFontSize(20).text('Reporte de agenda', M, 36);
    doc.setFont('helvetica', 'normal').setFontSize(10.5).text(clinica, M, 54);
    doc.text(`Periodo: del ${fmtDL(desde)} al ${fmtDL(hasta)}`, M, 68);
    y = 104;

    // ---------- Indicadores: tabla a todo lo ancho, un indicador por columna ----------
    titulo('Indicadores del periodo');
    autoTable(doc, {
        startY: y,
        head: [kpis.map((k) => k.l)],
        body: [kpis.map((k) => String(k.v))],
        theme: 'grid',
        styles: { halign: 'center', valign: 'middle', lineColor: [226, 232, 240], lineWidth: 0.6 },
        headStyles: { fillColor: NAVY, textColor: [255, 255, 255], fontSize: 8.5, fontStyle: 'bold', cellPadding: 6 },
        bodyStyles: { fontSize: 16, fontStyle: 'bold', textColor: NAVY, cellPadding: 9, fillColor: [248, 250, 252] },
        margin: { left: M, right: M },
        tableWidth: ancho,
    });
    y = doc.lastAutoTable.finalY + 24;

    // ---------- Una sección por gráfica ----------
    graficas.forEach((g) => {
        if (g.lado) {
            // Gráfica a la izquierda y su tabla a la derecha.
            const wi = ancho * 0.48;
            const hi = g.canvas ? Math.min(200, (g.canvas.height / g.canvas.width) * wi) : 0;
            saltoSi(34 + Math.max(hi, 24 + g.tabla.filas.length * 18));
            titulo(g.titulo);
            const inicio = y;
            if (g.canvas) {
                const w = (hi / g.canvas.height) * g.canvas.width;
                doc.addImage(imagen(g.canvas), 'JPEG', M + (wi - w) / 2, y, w, hi);
            }
            const fin = g.tabla?.filas.length ? tabla(g.tabla, M + wi + 16, ancho - wi - 16, inicio) : inicio;
            y = Math.max(inicio + hi, fin) + 22;
        } else {
            const hi = g.canvas ? Math.min(230, (g.canvas.height / g.canvas.width) * ancho) : 0;
            saltoSi(34 + hi + 40);
            titulo(g.titulo);
            if (g.canvas) {
                const w = (hi / g.canvas.height) * g.canvas.width;
                doc.addImage(imagen(g.canvas), 'JPEG', M + (ancho - w) / 2, y, w, hi);
                y += hi + 10;
            }
            if (g.tabla?.filas.length) y = tabla(g.tabla, M, ancho, y) + 22;
        }
    });

    // ---------- Pie con autor, fecha de emisión y paginación ----------
    const emitido = new Date();
    const total = doc.getNumberOfPages();
    for (let i = 1; i <= total; i++) {
        doc.setPage(i);
        doc.setDrawColor(226, 232, 240).setLineWidth(0.6).line(M, H - 30, W - M, H - 30);
        doc.setFont('helvetica', 'normal').setFontSize(8).setTextColor(...GRIS);
        doc.text(`Generado por ${usuario} el ${emitido.toLocaleString('es-MX')}`, M, H - 18);
        doc.text(`Página ${i} de ${total}`, W - M, H - 18, { align: 'right' });
    }
    doc.save(`reporte_${desde}_${hasta}.pdf`);
}
