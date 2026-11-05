// The lighthouse alone: sheet region x 670–846, y 118–416; in the lettering band (y < 388) only the tower columns
// (728–804) are kept, below it the shore (676–840). Writes a PNG for trace.js.
const Jimp = require('jimp');
(async () => {
  const [src, out] = process.argv.slice(2);
  const img = (await Jimp.read(src)).crop(670, 118, 176, 298);
  img.scan(0, 0, 176, 298, function (x, y, i) {
    const sx = x + 670; const sy = y + 118;
    const keep = sy < 388 ? sx >= 728 && sx <= 804 : sx >= 676 && sx <= 840;
    if (!keep) this.bitmap.data.writeUInt32BE(0xffffffff, i);
  });
  await img.writeAsync(out);
})();
