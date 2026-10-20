#!/usr/bin/env python3
"""Build original SIDRENA Windows icon and installer artwork, standard library only.

Produces a real multi-resolution ICO and two 24-bit BMP files with the
same brand geometry; does not need external fonts, images or services.
Run before dotnet build / Inno Setup packaging.
"""
from __future__ import annotations
import math
import pathlib
import struct
import zlib

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/"branding"/"generated"
OUT.mkdir(parents=True,exist_ok=True)
NAVY=(17,32,52)
NAVY_LIGHT=(32,57,83)
ORANGE=(255,133,44)
GOLD=(255,166,79)

def distance(x,y,x1,y1,x2,y2):
    vx=x2-x1;vy=y2-y1
    t=max(0.0,min(1.0,((x-x1)*vx+(y-y1)*vy)/(vx*vx+vy*vy))) if vx*vx+vy*vy else 0
    return math.hypot(x-(x1+vx*t),y-(y1+vy*t))

def sidrena_mark(u,v):
    """Shared stylized S geometry for the Windows icon and installer art."""
    top=distance(u,v,.72,.26,.32,.26)<.068
    bend_top=distance(u,v,.32,.26,.30,.42)<.064
    middle=distance(u,v,.30,.42,.70,.59)<.070
    bend_bottom=distance(u,v,.70,.59,.70,.74)<.064
    bottom=distance(u,v,.70,.74,.29,.74)<.068
    return top or bend_top or middle or bend_bottom or bottom

def pixel(x,y,w,h,round_corners=True):
    u=(x+0.5)/w;v=(y+0.5)/h
    if round_corners:
        r=.20
        dx=max(abs(u-.5)-(.5-r),0.0);dy=max(abs(v-.5)-(.5-r),0.0)
        if dx*dx+dy*dy>r*r:return (0,0,0,0)
    base=tuple(round(NAVY[i]+(NAVY_LIGHT[i]-NAVY[i])*(.3*u+.35*v)) for i in range(3))
    if sidrena_mark(u,v):
        tint=.12+u*.22
        return tuple(round(ORANGE[i]*(1-tint)+GOLD[i]*tint) for i in range(3))+(255,)
    return base+(255,)

def rgba_png(w,h):
    rows=[]
    for y in range(h):
        row=bytearray(b"\0")
        for x in range(w):row.extend(pixel(x,y,w,h))
        rows.append(bytes(row))
    def chunk(tag,data):
        return struct.pack(">I",len(data))+tag+data+struct.pack(">I",zlib.crc32(tag+data)&0xffffffff)
    return (b"\x89PNG\r\n\x1a\n"
        +chunk(b"IHDR",struct.pack(">IIBBBBB",w,h,8,6,0,0,0))
        +chunk(b"IDAT",zlib.compress(b"".join(rows),level=9))
        +chunk(b"IEND",b""))

icons=[(n,rgba_png(n,n)) for n in (16,24,32,48,64,128,256)]
header=struct.pack("<HHH",0,1,len(icons))
directory=bytearray()
data=bytearray()
offset=6+len(icons)*16
for n,payload in icons:
    directory.extend(struct.pack("<BBBBHHII",0 if n==256 else n,
        0 if n==256 else n,0,0,1,32,len(payload),offset))
    data.extend(payload);offset+=len(payload)
(OUT/"SIDRENA.ico").write_bytes(header+directory+data)

def bmp(w,h,small=False):
    rowlen=(w*3+3)&~3
    data=bytearray()
    for y in range(h-1,-1,-1):
        for x in range(w):
            # Windows welcome panel uses the same SIDRENA S mark as the application.
            u=x/max(1,w-1);v=y/max(1,h-1)
            base=tuple(round(NAVY[i]+(NAVY_LIGHT[i]-NAVY[i])*(.2+.4*v)) for i in range(3))
            if small:
                uu=(u-.06)/.88;vv=(v-.06)/.88
            else:
                uu=u;vv=(v-.26)/.48
            color=ORANGE if sidrena_mark(uu,vv) else base
            data.extend(bytes((color[2],color[1],color[0])))
        data.extend(b"\0"*(rowlen-w*3))
    length=54+len(data)
    return (b"BM"+struct.pack("<IHHI",length,0,0,54)
        +struct.pack("<IiiHHIIiiII",40,w,h,1,24,0,len(data),2835,2835,0,0)
        +bytes(data))
(OUT/"WizardLarge.bmp").write_bytes(bmp(164,314))
(OUT/"WizardSmall.bmp").write_bytes(bmp(55,55,True))
for file in OUT.iterdir():
    print(f"Brendigo visual asset: {file.name} ({file.stat().st_size} bytes)")
