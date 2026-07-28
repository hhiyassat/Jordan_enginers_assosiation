interface JEALogoProps {
  size?: number;
  dark?: boolean;
}

export function JEALogo({ size = 40, dark = false }: JEALogoProps) {
  const barColor  = dark ? '#FFFFFF' : '#1A77BC';
  const shapeMain = dark ? 'rgba(255,255,255,0.95)' : '#3A3A3A';
  const shapeSide = dark ? 'rgba(255,255,255,0.55)' : '#6A6A6A';

  const bars = [
    { x: 20,   h: 36, y: 8  },
    { x: 23.5, h: 34, y: 10 },
    { x: 27,   h: 31, y: 13 },
    { x: 30.5, h: 28, y: 16 },
    { x: 34,   h: 25, y: 19 },
    { x: 37.5, h: 21, y: 22 },
    { x: 41,   h: 18, y: 26 },
    { x: 44.5, h: 15, y: 29 },
    { x: 48,   h: 11, y: 32 },
    { x: 51.5, h: 8,  y: 36 },
    { x: 55,   h: 6,  y: 38 },
  ];

  return (
    <svg width={size} height={size * 0.9} viewBox="0 0 60 52" fill="none" xmlns="http://www.w3.org/2000/svg">
      <polygon points="3,46 3,8 15,2 15,38" fill={shapeMain} />
      <polygon points="3,46 15,38 15,46" fill={shapeSide} />
      <polygon points="7,46 7,30 15,22 15,38" fill={shapeSide} />
      {bars.map((b, i) => (
        <rect key={i} x={b.x} y={b.y} width={2.5} height={b.h} rx={0.5} fill={barColor} />
      ))}
    </svg>
  );
}
