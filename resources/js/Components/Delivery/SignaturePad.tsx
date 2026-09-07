import React, { useRef, useEffect, useState, useCallback } from 'react';
import { RotateCcw, PenTool } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface Point {
    x: number;
    y: number;
}

interface SignaturePadProps {
    onSignatureChange: (file: File | null) => void;
    className?: string;
    height?: number;
}

export const SignaturePad: React.FC<SignaturePadProps> = ({
    onSignatureChange,
    className = '',
    height = 160,
}) => {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [isDrawing, setIsDrawing] = useState(false);
    const [hasSignature, setHasSignature] = useState(false);
    const pointsRef = useRef<Point[]>([]);

    // Convert canvas data to File
    const exportSignature = useCallback(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        canvas.toBlob((blob) => {
            if (blob) {
                const file = new File([blob], `signature_${Date.now()}.png`, {
                    type: 'image/png',
                    lastModified: Date.now(),
                });
                onSignatureChange(file);
            }
        }, 'image/png');
    }, [onSignatureChange]);

    // Setup high-DPI canvas
    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        canvas.width = rect.width * dpr;
        canvas.height = height * dpr;

        ctx.scale(dpr, dpr);
        ctx.strokeStyle = '#059669'; // Emerald-600
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }, [height]);

    const getCanvasPoint = (e: React.PointerEvent<HTMLCanvasElement>): Point => {
        const canvas = canvasRef.current;
        if (!canvas) return { x: 0, y: 0 };

        const rect = canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        };
    };

    const startDrawing = (e: React.PointerEvent<HTMLCanvasElement>) => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        canvas.setPointerCapture(e.pointerId);
        setIsDrawing(true);
        const point = getCanvasPoint(e);
        pointsRef.current = [point];

        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.beginPath();
            ctx.arc(point.x, point.y, ctx.lineWidth / 2, 0, Math.PI * 2, !0);
            ctx.fillStyle = ctx.strokeStyle;
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(point.x, point.y);
        }
    };

    const draw = (e: React.PointerEvent<HTMLCanvasElement>) => {
        if (!isDrawing) return;

        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !ctx) return;

        const currentPoint = getCanvasPoint(e);
        pointsRef.current.push(currentPoint);

        const points = pointsRef.current;

        if (points.length > 2) {
            // Quadratic bezier curve midpoint interpolation for butter-smooth strokes
            const lastPoint = points[points.length - 2];
            const midPoint = {
                x: (lastPoint.x + currentPoint.x) / 2,
                y: (lastPoint.y + currentPoint.y) / 2,
            };

            ctx.quadraticCurveTo(lastPoint.x, lastPoint.y, midPoint.x, midPoint.y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(midPoint.x, midPoint.y);
        } else {
            ctx.lineTo(currentPoint.x, currentPoint.y);
            ctx.stroke();
        }

        if (!hasSignature) {
            setHasSignature(true);
        }
    };

    const stopDrawing = (e: React.PointerEvent<HTMLCanvasElement>) => {
        if (!isDrawing) return;

        const canvas = canvasRef.current;
        if (canvas && canvas.hasPointerCapture(e.pointerId)) {
            canvas.releasePointerCapture(e.pointerId);
        }

        setIsDrawing(false);
        pointsRef.current = [];
        exportSignature();
    };

    const clearSignature = () => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !ctx) return;

        const dpr = window.devicePixelRatio || 1;
        ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
        pointsRef.current = [];
        setHasSignature(false);
        onSignatureChange(null);
    };

    return (
        <div className={`space-y-2 ${className}`}>
            <div className="flex items-center justify-between">
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                    <PenTool className="w-3.5 h-3.5 text-emerald-400" />
                    <span>Recipient Signature</span>
                </label>
                {hasSignature && (
                    <button
                        type="button"
                        onClick={clearSignature}
                        className="text-[11px] font-medium text-slate-400 hover:text-rose-400 flex items-center gap-1 transition-colors"
                    >
                        <RotateCcw className="w-3 h-3" />
                        <span>Clear</span>
                    </button>
                )}
            </div>

            <div className="relative border border-slate-800 rounded-xl overflow-hidden bg-slate-950/70 touch-none">
                <canvas
                    ref={canvasRef}
                    style={{ height: `${height}px`, width: '100%' }}
                    className="w-full cursor-crosshair block touch-none"
                    onPointerDown={startDrawing}
                    onPointerMove={draw}
                    onPointerUp={stopDrawing}
                    onPointerCancel={stopDrawing}
                />
                {!hasSignature && (
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none text-slate-600 text-xs font-medium">
                        Sign with finger or stylus here
                    </div>
                )}
            </div>
        </div>
    );
};

export default SignaturePad;
