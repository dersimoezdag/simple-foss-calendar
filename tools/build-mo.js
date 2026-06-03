const fs = require('fs');
const path = require('path');

function unquote(value) {
  return JSON.parse(value);
}

function parsePo(content) {
  const entries = [];
  let entry = {};
  let current = null;

  function pushEntry() {
    if (Object.prototype.hasOwnProperty.call(entry, 'msgid')) {
      entries.push(entry);
    }
    entry = {};
    current = null;
  }

  content.split(/\r?\n/).forEach((line) => {
    if (!line.trim()) {
      pushEntry();
      return;
    }

    if (line.startsWith('#')) {
      return;
    }

    const match = line.match(/^(msgctxt|msgid|msgstr)\s+(".*")$/);
    if (match) {
      current = match[1];
      entry[current] = unquote(match[2]);
      return;
    }

    if (current && line.startsWith('"')) {
      entry[current] += unquote(line);
    }
  });

  pushEntry();
  return entries;
}

function writeMo(entries, outputPath) {
  const messages = entries.map((entry) => {
    const key = entry.msgctxt ? `${entry.msgctxt}\u0004${entry.msgid}` : entry.msgid;
    return [key, entry.msgstr || ''];
  }).sort((a, b) => a[0].localeCompare(b[0]));

  const count = messages.length;
  const headerSize = 28;
  const originalsOffset = headerSize;
  const translationsOffset = originalsOffset + count * 8;
  let stringsOffset = translationsOffset + count * 8;

  const originalBuffers = messages.map(([key]) => Buffer.from(key, 'utf8'));
  const translationBuffers = messages.map(([, value]) => Buffer.from(value, 'utf8'));
  const output = Buffer.alloc(stringsOffset + originalBuffers.reduce((sum, buffer) => sum + buffer.length + 1, 0) + translationBuffers.reduce((sum, buffer) => sum + buffer.length + 1, 0));

  output.writeUInt32LE(0x950412de, 0);
  output.writeUInt32LE(0, 4);
  output.writeUInt32LE(count, 8);
  output.writeUInt32LE(originalsOffset, 12);
  output.writeUInt32LE(translationsOffset, 16);
  output.writeUInt32LE(0, 20);
  output.writeUInt32LE(0, 24);

  originalBuffers.forEach((buffer, index) => {
    output.writeUInt32LE(buffer.length, originalsOffset + index * 8);
    output.writeUInt32LE(stringsOffset, originalsOffset + index * 8 + 4);
    buffer.copy(output, stringsOffset);
    stringsOffset += buffer.length + 1;
  });

  translationBuffers.forEach((buffer, index) => {
    output.writeUInt32LE(buffer.length, translationsOffset + index * 8);
    output.writeUInt32LE(stringsOffset, translationsOffset + index * 8 + 4);
    buffer.copy(output, stringsOffset);
    stringsOffset += buffer.length + 1;
  });

  fs.writeFileSync(outputPath, output);
}

const languagesDir = path.join(__dirname, '..', 'simple-foss-calendar', 'languages');
fs.readdirSync(languagesDir)
  .filter((file) => file.endsWith('.po'))
  .forEach((file) => {
    const inputPath = path.join(languagesDir, file);
    const outputPath = inputPath.replace(/\.po$/, '.mo');
    writeMo(parsePo(fs.readFileSync(inputPath, 'utf8')), outputPath);
    console.log(`Wrote ${outputPath}`);
  });
