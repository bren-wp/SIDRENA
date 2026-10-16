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
ORANGE=(255,145,63)
GOLD=(255,191,96)

def distance(x,y,x1,y1,x2,y2):
    vx=x2-x1;vy=y2-y1
    t=max(0.0,min(1.0,((x-x1)*vx+(y-y1)*vy)/(vx*vx+vy*vy))) if vx*vx+vy*vy else 0
    return math.hypot(x-(x1+vx*t),y-(y1+vy*t))

def pixel(x,y,w,h,round_corners=True):
    u=(x+0.5)/w;v=(y+0.5)/h
    if round_corners:
        r=.20
        dx=max(abs(u-.5)-(.5-r),0.0);dy=max(abs(v-.5)-(.5-r),0.0)
        if dx*dx+dy*dy>r*r:return (0,0,0,0)
    base=tuple(round(NAVY[i]+(NAVY_LIGHT[i]-NAVY[i])*(.3*u+.35*v)) for i in range(3))
    # anchor head, central mast and crossbar
    ring=abs(math.hypot((u-.5),v-.26)-.072)<.033
    mast=distance(u,v,.5,.35,.5,.72)<.034
    bar=distance(u,v,.35,.45,.65,.45)<.032
    # curved flukes / lower bowl, stopped at x=.22 and .78
    bowl=False
    if .21<u<.79 and .64<v<.84:
        bowl=abs(v-(.83-2.0*(u-.5)**2))<.034
    left=distance(u,v,.22,.68,.22,.78)<.034
    right=distance(u,v,.78,.68,.78,.78)<.034
    if ring or mast or bar or bowl or left or right:
        tint=.25+u*.5
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
            # Windows welcome panel: dark premium gradient and central orange anchor.
            u=x/max(1,w-1);v=y/max(1,h-1)
            base=tuple(round(NAVY[i]+(NAVY_LIGHT[i]-NAVY[i])*(.2+.4*v)) for i in range(3))
            if small:
                uu=(u-.06)/.88;vv=(v-.06)/.88
            else:
                uu=u;vv=(v-.26)/.48
            ring=abs(math.hypot(uu-.5,vv-.26)-.072)<.037
            mast=distance(uu,vv,.5,.35,.5,.72)<.038
            bar=distance(uu,vv,.35,.45,.65,.45)<.037
            bowl=.21<uu<.79 and .64<vv<.84 and abs(vv-(.83-2*(uu-.5)**2))<.04
            left=distance(uu,vv,.22,.68,.22,.78)<.037
            right=distance(uu,vv,.78,.68,.78,.78)<.037
            color=ORANGE if ring or mast or bar or bowl or left or right else base
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
